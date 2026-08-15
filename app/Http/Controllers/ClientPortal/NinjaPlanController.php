<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\DataMapper\Analytics\TrialStarted;
use App\DataMapper\Billing\BillingContext;
use App\Factory\RecurringInvoiceFactory;
use App\Http\Controllers\Controller;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Notifications\Ninja\NewAccountNotification;
use App\Repositories\RecurringInvoiceRepository;
use App\Repositories\SubscriptionRepository;
use App\Utils\Traits\MakesHash;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;
use Stripe\Exception\ApiErrorException;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Throwable;
use Turbo124\Beacon\Facades\LightLogs;

class NinjaPlanController extends Controller
{
    use MakesHash;

    public function index(string $contact_key, string $account_or_company_key)
    {
        MultiDB::findAndSetDbByCompanyKey($account_or_company_key);
        $company = Company::query()->where('company_key', $account_or_company_key)->first();

        if (! $company) {
            MultiDB::findAndSetDbByAccountKey($account_or_company_key);

            /** @var \App\Models\Account $account **/
            $account = Account::query()->where('key', $account_or_company_key)->first();
        } else {
            $account = $company->account;
        }

        if (MultiDB::findAndSetDbByContactKey($contact_key) && $client_contact = ClientContact::where('contact_key', $contact_key)->first()) {
            nlog('Ninja Plan Controller - Found and set Client Contact');

            request()->session()->invalidate();
            request()->session()->regenerateToken();
            Auth::guard('contact')->loginUsingId($client_contact->id, true);

            return $this->plan();
        }

        return redirect()->route('client.catchall');
    }

    public function trial()
    {
        $contact = Auth::guard('contact')->user();
        $client = $contact->client;

        if (
            ! $client->custom_value2
            || ! MultiDB::findAndSetDbByAccountKey($client->custom_value2)
            || ! ($account = Account::where('key', $client->custom_value2)->first())
            || ! $this->isTrialEligible($account)
        ) {
            return redirect()->route('client.plan');
        }

        MultiDB::setDB('db-ninja-01');

        $gateway = $this->trialGateway();
        $gatewayDriver = $gateway->driver($client)->init();
        $customer = $gatewayDriver->findOrCreateCustomer();
        $requestId = (string) Str::uuid();
        $paymentIntent = PaymentIntent::create([
            'amount' => 100,
            'currency' => 'usd',
            'capture_method' => 'manual',
            'setup_future_usage' => 'off_session',
            'payment_method_types' => ['card'],
            'customer' => $customer->id,
            'metadata' => [
                'purpose' => 'ninja_plan_trial_authorization',
                'client_id' => (string) $contact->client_id,
                'contact_id' => (string) $contact->id,
            ],
        ], array_merge($gatewayDriver->stripe_connect_auth, [
            'idempotency_key' => "ninja-plan-trial-{$requestId}",
        ]));

        $authorizations = request()->session()->get(
            'ninja_plan_trial_authorizations',
            []
        );
        $authorizations[$paymentIntent->id] = [
            'payment_intent_id' => $paymentIntent->id,
            'customer_id' => $customer->id,
            'client_id' => $contact->client_id,
            'contact_id' => $contact->id,
            'released' => false,
        ];
        request()->session()->put(
            'ninja_plan_trial_authorizations',
            array_slice($authorizations, -10, preserve_keys: true)
        );

        return $this->render('plan.trial', [
            'gateway' => $gateway,
            'intent' => $paymentIntent,
            'client' => $client,
        ]);
    }

    public function trial_confirmation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'starts_with:pi_'],
        ]);

        $profileRules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'integer', Rule::exists('countries', 'id')],
        ];

        /** @var \App\Models\ClientContact $contact **/
        $contact = Auth::guard('contact')->user();
        $authorization = $request->session()->get(
            "ninja_plan_trial_authorizations.{$validated['payment_intent_id']}"
        );

        if (
            ! is_array($authorization)
            || ! hash_equals((string) ($authorization['payment_intent_id'] ?? ''), $validated['payment_intent_id'])
            || (string) ($authorization['client_id'] ?? '') !== (string) $contact->client_id
            || (string) ($authorization['contact_id'] ?? '') !== (string) $contact->id
        ) {
            return response()->json([
                'message' => ctrans('texts.trial_card_verification_failed'),
            ], 422);
        }

        $client = $contact->client;
        $gateway = $this->trialGateway();
        $gatewayDriver = $gateway->driver($client)->init();

        try {
            $paymentIntent = $this->retrieveTrialPaymentIntent(
                $validated['payment_intent_id'],
                $gatewayDriver
            );
        } catch (ApiErrorException $exception) {
            report($exception);

            return response()->json([
                'message' => ctrans('texts.trial_card_gateway_error'),
            ], 502);
        }

        $method = $paymentIntent->payment_method;
        $released = (bool) ($authorization['released'] ?? false);

        if (
            ! $this->isValidTrialAuthorization($paymentIntent, $method, $authorization, $contact)
        ) {
            if ($paymentIntent->status === PaymentIntent::STATUS_REQUIRES_CAPTURE) {
                try {
                    $paymentIntent->cancel(
                        ['cancellation_reason' => 'requested_by_customer'],
                        $gatewayDriver->stripe_connect_auth
                    );
                } catch (ApiErrorException $exception) {
                    report($exception);

                    return response()->json([
                        'message' => ctrans('texts.trial_card_release_failed'),
                    ], 502);
                }
            }

            return response()->json([
                'message' => $method instanceof PaymentMethod
                    && (string) ($method->card->funding ?? '') !== 'credit'
                    ? ctrans('texts.trial_credit_card_required')
                    : ctrans('texts.trial_card_verification_failed'),
            ], 422);
        }

        if (! $released) {
            $cancelOptions = array_merge($gatewayDriver->stripe_connect_auth, [
                'idempotency_key' => "ninja-plan-trial-release-{$paymentIntent->id}",
            ]);

            try {
                $paymentIntent = $paymentIntent->cancel(
                    ['cancellation_reason' => 'requested_by_customer'],
                    $cancelOptions
                );
            } catch (ApiErrorException $exception) {
                report($exception);

                try {
                    $paymentIntent = $this->retrieveTrialPaymentIntent(
                        $validated['payment_intent_id'],
                        $gatewayDriver
                    );
                } catch (ApiErrorException $retrieveException) {
                    report($retrieveException);

                    return response()->json([
                        'message' => ctrans('texts.trial_card_release_failed'),
                    ], 502);
                }

                if ($paymentIntent->status === PaymentIntent::STATUS_REQUIRES_CAPTURE) {
                    try {
                        $paymentIntent = $paymentIntent->cancel(
                            ['cancellation_reason' => 'requested_by_customer'],
                            $cancelOptions
                        );
                    } catch (ApiErrorException $retryException) {
                        report($retryException);

                        return response()->json([
                            'message' => ctrans('texts.trial_card_release_failed'),
                        ], 502);
                    }
                }
            }

            if ($paymentIntent->status !== PaymentIntent::STATUS_CANCELED) {
                return response()->json([
                    'message' => ctrans('texts.trial_card_release_failed'),
                ], 502);
            }

            $authorization['released'] = true;
            $request->session()->put(
                "ninja_plan_trial_authorizations.{$validated['payment_intent_id']}",
                $authorization
            );
        }

        $validated = array_merge($validated, $request->validate($profileRules));

        if (! $client->custom_value2 || ! MultiDB::findAndSetDbByAccountKey($client->custom_value2)) {
            return response()->json([
                'message' => ctrans('texts.trial_no_longer_available'),
            ], 422);
        }

        MultiDB::setDB('db-ninja-01');

        $contact->fill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
        ])->save();

        $client->private_notes = 'Trial Started @ ' . now()->format('Y-m-d H:i:s');
        $client->fill(collect($validated)->only([
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
        ])->all());
        $client->country_id = $validated['country'];
        $client->save();

        try {
            Customer::update($authorization['customer_id'], [
                'name' => $client->present()->name(),
                'phone' => substr($client->present()->phone(), 0, 20),
                'address' => [
                    'line1' => $client->address1 ?: '',
                    'line2' => $client->address2 ?: '',
                    'city' => $client->city ?: '',
                    'postal_code' => $client->postal_code ?: '',
                    'state' => $client->state ?: '',
                    'country' => $client->country ? $client->country->iso_3166_2 : '',
                ],
            ], $gatewayDriver->stripe_connect_auth);
        } catch (ApiErrorException $exception) {
            report($exception);

            return response()->json([
                'message' => ctrans('texts.trial_card_gateway_error'),
            ], 502);
        }

        try {
            $provisioned = $this->withTrialProvisioningLock(
                $client->custom_value2,
                fn(): array => $this->provisionTrial(
                    $client,
                    $gateway,
                    $gatewayDriver,
                    $method,
                    $authorization,
                    $paymentIntent->id
                )
            );
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => ctrans('texts.trial_verification_in_progress'),
            ], 409);
        } catch (LogicException) {
            return response()->json([
                'message' => ctrans('texts.trial_no_longer_available'),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => ctrans('texts.trial_card_gateway_error'),
            ], 502);
        }

        $request->session()->forget(
            "ninja_plan_trial_authorizations.{$validated['payment_intent_id']}"
        );
        $request->session()->flash('ninja_plan_trial_confirmed', true);

        if ($provisioned['completed_now']) {
            $this->afterTrialProvisioned(
                $provisioned['account'],
                $provisioned['subscription'],
                $provisioned['recurring_invoice'],
                $client
            );
        }

        return response()->json([
            'redirect_url' => route('client.trial.confirmed'),
        ]);
    }

    public function trialConfirmed(): RedirectResponse|View
    {
        if (! request()->session()->pull('ninja_plan_trial_confirmed', false)) {
            return redirect()->route('client.plan');
        }

        return $this->render('plan.trial_confirmed');
    }

    protected function trialGateway(): CompanyGateway
    {
        return CompanyGateway::on('db-ninja-01')->findOrFail(
            config('ninja.ninja_default_company_gateway_id')
        );
    }

    protected function retrieveTrialPaymentIntent(
        string $paymentIntentId,
        mixed $gatewayDriver
    ): PaymentIntent {
        return PaymentIntent::retrieve([
            'id' => $paymentIntentId,
            'expand' => ['payment_method', 'review'],
        ], $gatewayDriver->stripe_connect_auth);
    }

    private function trialProvisioningLockKey(string $accountKey): string
    {
        return 'ninja-plan-trial:provision:' . hash(
            'sha256',
            config('database.default') . '|' . $accountKey
        );
    }

    /**
     * @param Closure(): array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private function withTrialProvisioningLock(
        string $accountKey,
        Closure $callback,
        int $waitSeconds = 5
    ): array {
        return Cache::lock($this->trialProvisioningLockKey($accountKey), 120)
            ->block($waitSeconds, $callback);
    }

    /**
     * @param array<string, mixed> $authorization
     * @return array{
     *     account: Account,
     *     subscription: Subscription,
     *     recurring_invoice: RecurringInvoice,
     *     completed_now: bool
     * }
     */
    private function provisionTrial(
        Client $client,
        CompanyGateway $gateway,
        mixed $gatewayDriver,
        PaymentMethod $method,
        array $authorization,
        string $paymentIntentId
    ): array {
        if (
            ! $client->custom_value2
            || ! MultiDB::findAndSetDbByAccountKey($client->custom_value2)
            || ! ($account = Account::query()->where('key', $client->custom_value2)->first())
        ) {
            throw new \RuntimeException('Unable to resolve the trial account.');
        }

        $accountDatabase = config('database.default');
        MultiDB::setDB('db-ninja-01');

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()->find(6);

        if (! $subscription) {
            throw new \RuntimeException('Unable to resolve the trial subscription.');
        }

        $marker = $this->trialRecurringInvoiceMarker($paymentIntentId);
        $checkpointedInvoice = $this->resolveCheckpointedTrialRecurringInvoice(
            $account,
            $client,
            $subscription
        );

        if ($account->trial_started) {
            if (
                ! $checkpointedInvoice
                || $checkpointedInvoice->status_id !== RecurringInvoice::STATUS_ACTIVE
            ) {
                throw new LogicException('The account has already used its trial.');
            }

            return [
                'account' => $account,
                'subscription' => $subscription,
                'recurring_invoice' => $checkpointedInvoice,
                'completed_now' => false,
            ];
        }

        $this->assertTrialAccountPending($account);

        $this->storeTrialGatewayToken(
            $client,
            $gateway,
            $gatewayDriver,
            $method,
            $authorization
        );

        $recurringInvoice = $checkpointedInvoice
            ?? $this->findTrialRecurringInvoiceByMarker($client, $subscription, $marker)
            ?? $this->createTrialRecurringInvoice($client, $subscription, $marker);

        MultiDB::setDB($accountDatabase);
        $account = Account::query()->where('key', $client->custom_value2)->firstOrFail();
        $this->assertTrialAccountPending($account);

        $existingCheckpointId = $account->billing_context?->recurring_invoice_id;

        if ($existingCheckpointId && $existingCheckpointId !== $recurringInvoice->id) {
            throw new \RuntimeException('The trial checkpoint changed during provisioning.');
        }

        $this->setTrialBillingContext($account, $client->id, $recurringInvoice->id);
        $account->save();

        $account = Account::query()->where('key', $client->custom_value2)->firstOrFail();

        if(class_exists(\Modules\Admin\Services\Evaluator::class)){
            app(\Modules\Admin\Services\Evaluator::class)->record($account, [
                'trial_started' => time(),
            ]);
        }

        if ($account->billing_context?->recurring_invoice_id !== $recurringInvoice->id) {
            throw new \RuntimeException('Unable to persist the trial checkpoint.');
        }

        MultiDB::setDB('db-ninja-01');
        $recurringInvoice = RecurringInvoice::query()
            ->without('client')
            ->findOrFail($recurringInvoice->id);
        $trialPeriod = $this->activateTrialRecurringInvoice(
            $recurringInvoice,
            $client,
            $subscription
        );
        $recurringInvoice = $trialPeriod['recurring_invoice'];

        MultiDB::setDB($accountDatabase);
        $account = Account::query()->where('key', $client->custom_value2)->firstOrFail();
        $this->assertTrialAccountPending($account);

        $accountCreatedAt = (int) $account->created_at;
        $this->finalizeTrialAccount(
            $account,
            $trialPeriod['started_at'],
            $trialPeriod['expires_at']
        );
        $account->setAttribute('trial_original_created_at', $accountCreatedAt);

        return [
            'account' => $account,
            'subscription' => $subscription,
            'recurring_invoice' => $recurringInvoice,
            'completed_now' => true,
        ];
    }

    private function assertTrialAccountPending(Account $account): void
    {
        if ($account->trial_started || $account->plan || $account->is_trial) {
            throw new LogicException('The account is not eligible for a trial.');
        }
    }

    private function trialRecurringInvoiceMarker(string $paymentIntentId): string
    {
        return "ninja_plan_trial:{$paymentIntentId}";
    }

    private function setTrialBillingContext(
        Account $account,
        int $clientId,
        int $recurringInvoiceId
    ): void {
        $billingContext = $account->billing_context ?? new BillingContext();
        $billingContext->client_id = $clientId;
        $billingContext->recurring_invoice_id = $recurringInvoiceId;
        $account->billing_context = $billingContext;
    }

    /**
     * @param array<string, mixed> $authorization
     */
    private function storeTrialGatewayToken(
        Client $client,
        CompanyGateway $gateway,
        mixed $gatewayDriver,
        PaymentMethod $method,
        array $authorization
    ): ClientGatewayToken {
        $paymentMeta = new \stdClass();
        $paymentMeta->exp_month = (string) $method->card->exp_month;
        $paymentMeta->exp_year = (string) $method->card->exp_year;
        $paymentMeta->brand = (string) $method->card->brand;
        $paymentMeta->last4 = (string) $method->card->last4;
        $paymentMeta->type = GatewayType::CREDIT_CARD;

        $token = ClientGatewayToken::withTrashed()
            ->where('company_id', $client->company_id)
            ->where('client_id', $client->id)
            ->where('company_gateway_id', $gateway->id)
            ->where('gateway_type_id', GatewayType::CREDIT_CARD)
            ->where('token', $method->id)
            ->first();

        if (! $token) {
            return $gatewayDriver->storeGatewayToken([
                'payment_meta' => $paymentMeta,
                'token' => $method->id,
                'payment_method_id' => GatewayType::CREDIT_CARD,
            ], [
                'gateway_customer_reference' => $authorization['customer_id'],
            ]);
        }

        if ($token->trashed()) {
            $token->restore();
        }

        ClientGatewayToken::query()
            ->where('client_id', $client->id)
            ->where('id', '!=', $token->id)
            ->update(['is_default' => false]);

        $token->gateway_customer_reference = $authorization['customer_id'];
        $token->meta = $paymentMeta;
        $token->is_deleted = false;
        $token->is_default = true;
        $token->save();

        return $token;
    }

    private function resolveCheckpointedTrialRecurringInvoice(
        Account $account,
        Client $client,
        Subscription $subscription
    ): ?RecurringInvoice {
        $billingContext = $account->billing_context;

        if (! $billingContext || ! $billingContext->recurring_invoice_id) {
            return null;
        }

        if ($billingContext->client_id !== $client->id) {
            throw new \RuntimeException('The trial checkpoint belongs to another client.');
        }

        $recurringInvoice = RecurringInvoice::query()
            ->without('client')
            ->whereKey($billingContext->recurring_invoice_id)
            ->where('company_id', $subscription->company_id)
            ->where('client_id', $client->id)
            ->where('subscription_id', $subscription->id)
            ->where('is_deleted', false)
            ->first();

        if (! $recurringInvoice) {
            throw new \RuntimeException('The trial checkpoint is invalid.');
        }

        $this->assertValidTrialRecurringInvoice($recurringInvoice, $client, $subscription);

        return $recurringInvoice;
    }

    /**
     * @return array{
     *     recurring_invoice: RecurringInvoice,
     *     started_at: Carbon,
     *     expires_at: Carbon
     * }
     */
    protected function activateTrialRecurringInvoice(
        RecurringInvoice $recurringInvoice,
        Client $client,
        Subscription $subscription
    ): array {
        $this->assertValidTrialRecurringInvoice($recurringInvoice, $client, $subscription);

        if ($recurringInvoice->status_id === RecurringInvoice::STATUS_DRAFT) {
            $trialStartedAt = now();
            $trialExpiresAt = $trialStartedAt->copy()->addDays(14);
            $recurringInvoice->date = $trialExpiresAt->format('Y-m-d');
            $recurringInvoice->next_send_date = $trialExpiresAt->format('Y-m-d');
            $recurringInvoice->next_send_date_client = $trialExpiresAt->format('Y-m-d');
            $recurringInvoice->save();
            $recurringInvoice = $this->startTrialRecurringInvoice($recurringInvoice);
        } else {
            $trialExpiresAt = Carbon::parse($recurringInvoice->next_send_date);
            $trialStartedAt = $trialExpiresAt->copy()->subDays(14);
        }

        if ($recurringInvoice->status_id !== RecurringInvoice::STATUS_ACTIVE) {
            throw new \RuntimeException('The trial recurring invoice is not active.');
        }

        return [
            'recurring_invoice' => $recurringInvoice,
            'started_at' => $trialStartedAt,
            'expires_at' => $trialExpiresAt,
        ];
    }

    protected function startTrialRecurringInvoice(
        RecurringInvoice $recurringInvoice
    ): RecurringInvoice {
        $recurringInvoice->calc()->getRecurringInvoice();
        $recurringInvoice->service()->applyNumber()->start()->save();

        return RecurringInvoice::query()
            ->without('client')
            ->findOrFail($recurringInvoice->id);
    }

    private function finalizeTrialAccount(
        Account $account,
        Carbon $trialStartedAt,
        Carbon $trialExpiresAt
    ): void {
        $this->assertTrialAccountPending($account);
        $account->plan = Account::PLAN_PRO;
        $account->plan_term = Account::PLAN_TERM_MONTHLY;
        $account->plan_started = $trialStartedAt;
        $account->plan_expires = $trialExpiresAt;
        $account->is_trial = true;
        $account->hosted_company_count = 10;
        $account->trial_plan = Account::PLAN_PRO;
        $account->created_at = now();
        $account->trial_started = $trialStartedAt;
        $account->save();
    }

    private function findTrialRecurringInvoiceByMarker(
        Client $client,
        Subscription $subscription,
        string $marker
    ): ?RecurringInvoice {
        return RecurringInvoice::query()
            ->without('client')
            ->where('company_id', $subscription->company_id)
            ->where('client_id', $client->id)
            ->where('subscription_id', $subscription->id)
            ->where('private_notes', $marker)
            ->where('is_deleted', false)
            ->latest('id')
            ->first();
    }

    private function assertValidTrialRecurringInvoice(
        RecurringInvoice $recurringInvoice,
        Client $client,
        Subscription $subscription
    ): void {
        if (! $this->isValidTrialRecurringInvoice($recurringInvoice, $client, $subscription)) {
            throw new \RuntimeException('The trial recurring invoice is invalid.');
        }
    }

    private function isValidTrialRecurringInvoice(
        RecurringInvoice $recurringInvoice,
        Client $client,
        Subscription $subscription
    ): bool {
        return $recurringInvoice->company_id === $subscription->company_id
            && $recurringInvoice->client_id === $client->id
            && $recurringInvoice->subscription_id === $subscription->id
            && ! $recurringInvoice->is_deleted
            && (bool) preg_match(
                '/^ninja_plan_trial:pi_[A-Za-z0-9_]+$/',
                (string) $recurringInvoice->private_notes
            )
            && in_array(
                $recurringInvoice->status_id,
                [RecurringInvoice::STATUS_DRAFT, RecurringInvoice::STATUS_ACTIVE],
                true
            )
            && (
                $recurringInvoice->status_id === RecurringInvoice::STATUS_DRAFT
                || ! empty($recurringInvoice->next_send_date)
            );
    }

    private function createTrialRecurringInvoice(
        Client $client,
        Subscription $subscription,
        string $marker
    ): RecurringInvoice {
        $subscriptionRepository = new SubscriptionRepository();
        $recurringInvoice = RecurringInvoiceFactory::create(
            $subscription->company_id,
            $subscription->user_id
        );
        $recurringInvoice->client_id = $client->id;
        $recurringInvoice->line_items = $subscriptionRepository->generateLineItems(
            $subscription,
            true,
            false
        );
        $recurringInvoice->subscription_id = $subscription->id;
        $recurringInvoice->frequency_id = $subscription->frequency_id
            ?: RecurringInvoice::FREQUENCY_MONTHLY;
        $recurringInvoice->date = now()->addDays(14);
        $recurringInvoice->remaining_cycles = -1;
        $recurringInvoice->auto_bill = $client->getSetting('auto_bill');
        $recurringInvoice->auto_bill_enabled = $this->setAutoBillFlag(
            $recurringInvoice->auto_bill
        );
        $recurringInvoice->due_date_days = 'terms';
        $recurringInvoice->next_send_date = now()->addDays(14)->format('Y-m-d');
        $recurringInvoice->next_send_date_client = now()->addDays(14)->format('Y-m-d');
        $recurringInvoice->private_notes = $marker;
        $recurringInvoice->save();

        return $recurringInvoice;
    }

    private function afterTrialProvisioned(
        Account $account,
        Subscription $subscription,
        RecurringInvoice $recurringInvoice,
        Client $client
    ): void {
        try {
            if (class_exists(\Modules\Admin\Jobs\Account\AccountStatus::class)) {
                \Modules\Admin\Jobs\Account\AccountStatus::dispatch(
                    (string) $account->key,
                    (int) $account->getAttribute('trial_original_created_at')
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        MultiDB::setDB('db-ninja-01');

        try {
            LightLogs::create(new TrialStarted())->increment()->queue();
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $oldRecurring = RecurringInvoice::query()
                ->where('company_id', config('ninja.ninja_default_company_id'))
                ->where('client_id', $client->id)
                ->where('id', '!=', $recurringInvoice->id)
                ->first();

            if ($oldRecurring) {
                $oldRecurring->service()->stop()->save();
                (new RecurringInvoiceRepository())->archive($oldRecurring);
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $ninjaCompany = Company::on('db-ninja-01')
                ->find(config('ninja.ninja_default_company_id'));
            $ninjaCompany?->notification(
                new NewAccountNotification($subscription->company->account, $client)
            )->ninja();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param array<string,mixed> $authorization
     */
    private function isValidTrialAuthorization(
        PaymentIntent $paymentIntent,
        mixed $method,
        array $authorization,
        ClientContact $contact
    ): bool {
        $released = (bool) ($authorization['released'] ?? false);

        return (
            $paymentIntent->status === PaymentIntent::STATUS_REQUIRES_CAPTURE
            || ($released && $paymentIntent->status === PaymentIntent::STATUS_CANCELED)
        )
            && $paymentIntent->capture_method === 'manual'
            && $paymentIntent->amount === 100
            && ($released || $paymentIntent->amount_capturable === 100)
            && $paymentIntent->currency === 'usd'
            && $paymentIntent->customer === ($authorization['customer_id'] ?? null)
            && $paymentIntent->livemode === app()->environment('production')
            && $paymentIntent->review === null
            && (string) ($paymentIntent->metadata->client_id ?? '') === (string) $contact->client_id
            && (string) ($paymentIntent->metadata->contact_id ?? '') === (string) $contact->id
            && $method instanceof PaymentMethod
            && $method->type === PaymentMethod::TYPE_CARD
            && (string) ($method->card->funding ?? '') === 'credit'
            && $method->customer === ($authorization['customer_id'] ?? null);
    }

    private function setAutoBillFlag($auto_bill)
    {
        if ($auto_bill == 'always' || $auto_bill == 'optout') {
            return true;
        }

        return false;
    }

    private function isTrialEligible(Account $account): bool
    {
        return ! $account->plan
            && ! $account->is_trial
            && empty($account->trial_started);
    }

    public function plan()
    {
        // return $this->trial();
        //harvest the current plan
        $data = [];
        $data['late_invoice'] = false;

        if (MultiDB::findAndSetDbByAccountKey(Auth::guard('contact')->user()->client->custom_value2)) {
            $account = Account::query()->where('key', Auth::guard('contact')->user()->client->custom_value2)->first();

            if ($account) {
                //offer the option to have a free trial
                if ($this->isTrialEligible($account)) {
                    return $this->trial();
                }

                if (Carbon::parse($account->plan_expires)->lt(now())) {
                    //expired get the most recent invoice for payment

                    $late_invoice = Invoice::on('db-ninja-01')
                                           ->where('company_id', Auth::guard('contact')->user()->company->id)
                                           ->where('client_id', Auth::guard('contact')->user()->client->id)
                                           ->where('status_id', Invoice::STATUS_SENT)
                                           ->whereNotNull('subscription_id')
                                           ->orderBy('id', 'DESC')
                                           ->first();

                    $data['late_invoice'] = false;
                }

                $recurring_invoice = RecurringInvoice::on('db-ninja-01')
                                            ->where('client_id', auth()->guard('contact')->user()->client->id)
                                            ->where('company_id', Auth::guard('contact')->user()->company->id)
                                            ->whereNotNull('subscription_id')
                                            ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                            ->orderBy('id', 'desc')
                                            ->first();

                $monthly_plans = Subscription::on('db-ninja-01')
                                             ->where('company_id', Auth::guard('contact')->user()->company->id)
                                             ->where('group_id', 6)
                                             ->orderBy('promo_price', 'ASC')
                                             ->get();

                $yearly_plans = Subscription::on('db-ninja-01')
                                             ->where('company_id', Auth::guard('contact')->user()->company->id)
                                             ->where('group_id', 31)
                                             ->orderBy('promo_price', 'ASC')
                                             ->get();

                $monthly_plans = $monthly_plans->merge($yearly_plans);

                $current_subscription_id = $recurring_invoice ? $this->encodePrimaryKey($recurring_invoice->subscription_id) : false;

                //remove existing subscription
                if ($current_subscription_id) {
                    $monthly_plans = $monthly_plans->filter(function ($plan) use ($current_subscription_id) {
                        return (string) $plan->hashed_id != (string) $current_subscription_id;
                    });
                }

                $data['account'] = $account;
                $data['client'] = Auth::guard('contact')->user()->client;
                $data['plans'] = $monthly_plans;
                $data['current_subscription_id'] = $current_subscription_id;
                $data['current_recurring_id'] = $recurring_invoice ? $recurring_invoice->hashed_id : false;

                return $this->render('plan.index', $data);
            }
        } else {
            return redirect('/client/dashboard');
        }
    }
}
