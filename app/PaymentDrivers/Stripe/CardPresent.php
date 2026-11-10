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

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\TerminalException;
use App\Jobs\Util\SystemLogger;
use App\Models\Currency;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\Models\TerminalPaymentIntent;
use App\Models\User;
use App\PaymentDrivers\Stripe\Terminal\RecordTerminalPayment;
use App\PaymentDrivers\Stripe\Terminal\SurchargePolicy;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Number;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\Account;
use Stripe\Charge;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Terminal\ConnectionToken;
use Stripe\Terminal\Location;

/* In-person card payments (Stripe Terminal): the device reads the card, we create and record every payment */
class CardPresent
{
    /* Stripe's minor unit exponent for each Terminal currency */
    public const CURRENCY_EXPONENTS = [
        'usd' => 2, 'cad' => 2, 'gbp' => 2, 'eur' => 2, 'aud' => 2, 'nzd' => 2, 'sgd' => 2, 'chf' => 2,
        'dkk' => 2, 'sek' => 2, 'nok' => 2, 'czk' => 2, 'pln' => 2, 'myr' => 2, 'jpy' => 0,
    ];

    /* Address fields Stripe requires for a Terminal location */
    private const LOCATION_FIELDS = [
        'US' => ['line1', 'city', 'state', 'postal_code'], 'CA' => ['line1', 'city', 'state', 'postal_code'],
        'AU' => ['line1', 'city', 'state', 'postal_code'], 'IT' => ['line1', 'city', 'state', 'postal_code'],
        'ES' => ['line1', 'city', 'state', 'postal_code'], 'JP' => ['line1', 'city', 'state', 'postal_code'],
        'IE' => ['line1', 'postal_code'], 'SG' => ['line1', 'postal_code'],
    ];

    private const CANCELLABLE = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

    public function __construct(public StripePaymentDriver $stripe)
    {
    }

    public function surcharge(): SurchargePolicy
    {
        return new SurchargePolicy($this);
    }

    public function record(TerminalPaymentIntent $collection, ?PaymentIntent $pi = null): array
    {
        return (new RecordTerminalPayment($this))->run($collection, $pi);
    }

    /* Scoped to one of the gateway's own Locations: an unscoped token reaches every reader on the account */
    public function connectionToken(string $location_id): string
    {
        if (!collect($this->locations())->contains('id', $location_id)) {
            throw new TerminalException('location_required', 422);
        }

        $this->stripe->init();

        return ConnectionToken::create(['location' => $location_id], $this->stripe->stripe_connect_auth)->secret;
    }

    public function locations(): array
    {
        return Cache::remember($this->cacheKey('locations'), 600, function () {
            $this->stripe->init();

            return collect(Location::all(['limit' => 100], $this->stripe->stripe_connect_auth)->data)
                ->map(fn ($location) => ['id' => $location->id, 'display_name' => $location->display_name])
                ->toArray();
        });
    }

    public function createLocation(): array
    {
        $company = $this->stripe->company_gateway->company;
        $country = $company->country()?->iso_3166_2;

        $address = array_filter([
            'line1' => trim($company->settings->address1 ?? ''),
            'line2' => trim($company->settings->address2 ?? ''),
            'city' => trim($company->settings->city ?? ''),
            'state' => trim($company->settings->state ?? ''),
            'postal_code' => trim($company->settings->postal_code ?? ''),
            'country' => $country,
        ]);

        $required = array_merge(self::LOCATION_FIELDS[$country] ?? ['line1', 'city', 'postal_code'], ['country']);

        if ($missing = array_values(array_diff($required, array_keys($address)))) {
            throw new TerminalException('location_address_required', 422, ['missing' => $missing]);
        }

        $this->stripe->init();

        $location = Location::create([
            'display_name' => Str::limit($company->present()->name(), 100, ''),
            'address' => $address,
        ], $this->stripe->stripe_connect_auth);

        Cache::forget($this->cacheKey('locations'));

        return ['id' => $location->id, 'display_name' => $location->display_name];
    }

    public function acknowledgeSurcharge(User $user): void
    {
        $settings = $this->stripe->company_gateway->settings ?? new \stdClass();
        $settings->terminal_surcharge_acknowledged_at = now()->toIso8601String();
        $settings->terminal_surcharge_acknowledged_by = $user->hashed_id;

        $this->stripe->company_gateway->setSettings($settings);
    }

    public function account(): array
    {
        return Cache::remember($this->cacheKey('account'), 600, function () {
            $this->stripe->init();

            $account = Account::retrieve(null, $this->stripe->stripe_connect_auth);

            return [
                'country' => strtoupper($account->country ?? ''),
                'default_currency' => strtolower($account->default_currency ?? ''),
                'card_payments_active' => ($account->charges_enabled ?? false) && ($account->capabilities->card_payments ?? 'active') == 'active',
            ];
        });
    }

    public function livemode(): bool
    {
        $key = $this->stripe->stripe_connect ? config('ninja.ninja_stripe_key') : $this->stripe->company_gateway->getConfigField('apiKey');

        return str_contains($key ?? '', '_live_');
    }

    /* One PaymentIntent per collection, at the base amount */
    public function createPaymentIntent(User $user, Invoice $invoice, array $data): array
    {
        $existing = TerminalPaymentIntent::query()
            ->where('company_id', $invoice->company_id)
            ->where('collection_id', $data['collection_id'])
            ->first();

        if ($existing) {
            return $this->resume($existing, $invoice, $data);
        }

        $account = $this->account();

        if (!$account['card_payments_active']) {
            throw new TerminalException('not_enabled', 422);
        }

        $currency = $invoice->client->currency();

        if ((self::CURRENCY_EXPONENTS[strtolower($currency->code)] ?? -1) != $currency->precision || strtolower($currency->code) != $account['default_currency']) {
            throw new TerminalException('unsupported_currency', 422, [], ['currency' => strtoupper($account['default_currency'])]);
        }

        $this->settleOpenCollections($invoice);

        $collection = DB::transaction(function () use ($user, $invoice, $data) {
            /** @var Invoice $invoice */
            $invoice = Invoice::withTrashed()->lockForUpdate()->find($invoice->id);

            $open = TerminalPaymentIntent::query()
                ->where('company_id', $invoice->company_id)
                ->where('invoice_id', $invoice->id)
                ->whereIn('status_id', TerminalPaymentIntent::OPEN_STATUSES)
                ->first();

            if ($open) {
                throw $this->collectionOpen($open);
            }

            $this->validateAmount($invoice, $data['amount']);

            /* Quoted before a draft is sent, so locking on send does not refuse the surcharge it was shown */
            $quote = $this->surcharge()->quote($invoice, $data['amount'], $data['apply_fee'], true);

            if ($this->toStripe($invoice, $data['expected_total']) != $this->toStripe($invoice, $quote['total'])) {
                throw new TerminalException('amount_mismatch', 409, ['quote' => $quote]);
            }

            if ($invoice->status_id == Invoice::STATUS_DRAFT) {
                if ($invoice->company->verifactuEnabled()) {
                    throw new TerminalException('send_invoice_first', 422);
                }

                $invoice = $invoice->service()->markSent()->save();
            }

            $payment_hash = PaymentHash::create([
                'hash' => Str::random(32),
                'data' => [
                    'amount_with_fee' => $quote['amount'],
                    'fee_net' => 0,
                    'invoices' => [
                        [
                            'invoice_id' => $invoice->hashed_id,
                            'amount' => $quote['amount'],
                            'invoice_number' => $invoice->number,
                            'pre_payment' => false,
                        ],
                    ],
                ],
                'fee_total' => 0,
                'fee_invoice_id' => $invoice->id,
            ]);

            return TerminalPaymentIntent::create([
                'company_id' => $invoice->company_id,
                'user_id' => $user->id,
                'invoice_id' => $invoice->id,
                'company_gateway_id' => $this->stripe->company_gateway->id,
                'payment_hash_id' => $payment_hash->id,
                'collection_id' => $data['collection_id'],
                'currency' => strtolower($invoice->client->currency()->code),
                'livemode' => $this->livemode(),
                'amount' => $quote['amount'],
                'fee_gross' => $quote['fee_gross'],
                'fee_net' => $quote['fee_net'],
                'apply_fee' => $quote['fee_gross'] > 0,
                'status_id' => TerminalPaymentIntent::STATUS_CREATING,
                'touched_at' => now(),
            ]);
        });

        return $this->openPaymentIntent($collection);
    }

    /**
     * After the card is read and before it is confirmed (Stripe allows 30 seconds).
     * A credit card with a surcharge: the PaymentIntent is raised, the card read again,
     * and finalize called again, since the customer may present another card.
     */
    public function finalize(TerminalPaymentIntent $collection): array
    {
        if ($collection->status_id != TerminalPaymentIntent::STATUS_OPEN || !$collection->payment_intent_id || !$collection->invoice) {
            throw new TerminalException('collection_closed');
        }

        $invoice = $collection->invoice;

        /* The client's currency changed since: the PaymentIntent is in one the invoice no longer is */
        if (strtolower($invoice->client->currency()->code) != $collection->currency) {
            throw new TerminalException('unsupported_currency', 422, [], ['currency' => strtoupper($collection->currency)]);
        }

        /* Before every decision, for every card: the invoice must still take the amount */
        if ($invoice->is_deleted || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED])
            || $this->toStripe($invoice, $invoice->balance) < $this->toStripe($invoice, $collection->amount)) {
            throw new TerminalException('balance_changed', 409, ['balance' => $invoice->balance]);
        }

        /* No surcharge asked for: nothing to read from Stripe */
        if (!$collection->apply_fee) {
            $this->setSurcharge($collection, TerminalPaymentIntent::SURCHARGE_PATH_NONE, 0);

            return $this->finalizeResponse($collection, false);
        }

        $this->stripe->init();

        $pi = PaymentIntent::retrieve(['id' => $collection->payment_intent_id, 'expand' => ['payment_method']], $this->stripe->stripe_connect_auth);
        $collected = $pi->status == 'requires_confirmation';
        $funding = ($pi->payment_method->type ?? null) == 'card_present' ? ($pi->payment_method->card_present->funding ?? null) : null;

        /*
         * The invoice's own rules were checked when the surcharge was quoted and the
         * PaymentIntent created: creating it sent a draft, which may have locked it. Only what
         * can change by now is checked again: the card, and the admin's settings.
         */
        $surcharge_allowed = $this->surcharge()->check()['allowed'];

        /* After the amount was raised: confirm a credit card, otherwise drop the surcharge and read again */
        if ($collection->surcharge_path == TerminalPaymentIntent::SURCHARGE_PATH_RECOLLECT) {
            /* Set back first, even when the card was not read: it is read again at that amount */
            if ($this->resetAmount($collection, $pi, $collection->fee_gross) || !$collected) {
                return $this->finalizeResponse($collection, true);
            }

            if ($funding == 'credit' && $surcharge_allowed) {
                return $this->finalizeResponse($collection, false, $funding);
            }

            PaymentIntent::update($pi->id, ['amount' => $this->toStripe($invoice, $collection->amount), 'description' => $this->description($invoice)], $this->stripe->stripe_connect_auth);
            $this->setSurcharge($collection, TerminalPaymentIntent::SURCHARGE_PATH_NONE, 0);

            return $this->finalizeResponse($collection, true, $funding);
        }

        if (!$collected) {
            throw new TerminalException('not_collected', 409, ['status' => $pi->status]);
        }

        if ($funding != 'credit' || !$surcharge_allowed) {
            $this->setSurcharge($collection, TerminalPaymentIntent::SURCHARGE_PATH_NONE, 0);

            return $this->finalizeResponse($collection, $this->resetAmount($collection, $pi, 0), $funding);
        }

        PaymentIntent::update($pi->id, [
            'amount' => $this->toStripe($invoice, $collection->amount + $collection->fee_gross),
            'description' => $this->description($invoice, $collection->fee_gross),
        ], $this->stripe->stripe_connect_auth);

        $this->setSurcharge($collection, TerminalPaymentIntent::SURCHARGE_PATH_RECOLLECT, $collection->fee_gross);

        return $this->finalizeResponse($collection, true, $funding);
    }

    /* Cancels only what Stripe cancels: a PaymentIntent in flight is left to be recorded */
    public function cancel(TerminalPaymentIntent $collection): array
    {
        if ($collection->isFinal()) {
            return $this->record($collection);
        }

        if (!$collection->payment_intent_id) {
            $collection->update(['status_id' => TerminalPaymentIntent::STATUS_FAILED]);

            return ['status' => 'canceled'];
        }

        $this->stripe->init();

        $pi = PaymentIntent::retrieve(['id' => $collection->payment_intent_id, 'expand' => ['latest_charge']], $this->stripe->stripe_connect_auth);

        if ($pi->status == 'succeeded') {
            return $this->record($collection, $pi);
        }

        if (in_array($pi->status, self::CANCELLABLE)) {
            $pi = $pi->cancel([], $this->stripe->stripe_connect_auth);
        }

        if ($pi->status != 'canceled') {
            return ['status' => $pi->status];
        }

        $collection->update(['status_id' => TerminalPaymentIntent::STATUS_CANCELLED]);

        return ['status' => 'canceled'];
    }

    /* Stripe's own receipt carries the card network fields */
    public function receipt(TerminalPaymentIntent $collection, string $email): void
    {
        if ($collection->status_id != TerminalPaymentIntent::STATUS_RECORDED || !$collection->payment) {
            throw new TerminalException('collection_closed');
        }

        /* Stripe sends it, outside the account's email limits: a few per payment */
        if ($collection->receipts_sent >= 3) {
            throw new TerminalException('receipt_limit', 429);
        }

        $this->stripe->init();

        Charge::update($collection->payment->transaction_reference, ['receipt_email' => $email], $this->stripe->stripe_connect_auth);

        $collection->increment('receipts_sent');
    }

    public static function isCardPresent(array $object): bool
    {
        $types = array_merge((array) ($object['payment_method_types'] ?? []), [$object['payment_method_details']['type'] ?? '']);

        return in_array('card_present', $types) || in_array('interac_present', $types);
    }

    public function toStripe(Invoice $invoice, float $amount, ?Currency $currency = null): int
    {
        $currency ??= $invoice->client->currency();

        /* Rounded, not truncated: a zero-decimal currency comes back as the raw float */
        return (int) round($this->stripe->convertToStripeAmount($amount, $currency->precision, $currency));
    }

    /* Charged money that was not recorded as expected */
    public function alert(TerminalPaymentIntent $collection, string $message): void
    {
        $this->log($collection, SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE, $message);

        \Sentry\captureMessage("Terminal payment {$collection->payment_intent_id}: {$message}");
    }

    /* Never the PaymentIntent itself: it carries a client secret and cardholder data */
    public function log(TerminalPaymentIntent $collection, int $event_id, string $message): void
    {
        nlog("terminal: {$message} {$collection->payment_intent_id}");

        SystemLogger::dispatch(
            ['message' => $message, 'collection_id' => $collection->collection_id, 'payment_intent' => $collection->payment_intent_id, 'amount' => $collection->amount],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            $event_id,
            SystemLog::TYPE_STRIPE,
            $collection->invoice?->client,
            $collection->company,
        );
    }

    private function resume(TerminalPaymentIntent $collection, Invoice $invoice, array $data): array
    {
        if ($collection->invoice_id != $invoice->id || $collection->company_gateway_id != $this->stripe->company_gateway->id
            || $this->toStripe($invoice, $collection->amount) != $this->toStripe($invoice, $data['amount']) || $collection->apply_fee != $data['apply_fee']) {
            throw new TerminalException('amount_mismatch');
        }

        if (in_array($collection->status_id, [TerminalPaymentIntent::STATUS_CANCELLED, TerminalPaymentIntent::STATUS_FAILED, TerminalPaymentIntent::STATUS_REJECTED])) {
            throw new TerminalException('collection_closed');
        }

        if ($collection->isFinal()) {
            throw new TerminalException('already_paid');
        }

        if (!$collection->payment_intent_id) {
            return $this->openPaymentIntent($collection);
        }

        $this->stripe->init();

        $pi = PaymentIntent::retrieve(['id' => $collection->payment_intent_id, 'expand' => ['latest_charge']], $this->stripe->stripe_connect_auth);

        if ($pi->status == 'succeeded') {
            $this->record($collection, $pi);

            throw new TerminalException('already_paid');
        }

        if ($pi->status == 'canceled') {
            $collection->update(['status_id' => TerminalPaymentIntent::STATUS_CANCELLED]);

            throw new TerminalException('collection_closed');
        }

        $collection->update(['touched_at' => now()]);

        return $this->intentResponse($collection, $pi);
    }

    private function openPaymentIntent(TerminalPaymentIntent $collection): array
    {
        $invoice = $collection->invoice;

        $this->stripe->init();

        try {
            $pi = PaymentIntent::create([
                'amount' => $this->toStripe($invoice, $collection->amount),
                'currency' => $collection->currency,
                'payment_method_types' => $collection->currency == 'cad' ? ['card_present', 'interac_present'] : ['card_present'],
                'capture_method' => 'automatic',
                'description' => $this->description($invoice),
                'metadata' => [
                    'payment_hash' => $collection->payment_hash->hash,
                    'gateway_type_id' => (string) GatewayType::CARD_PRESENT,
                    'invoice_id' => (string) $invoice->hashed_id,
                    'invoice_number' => (string) $invoice->number,
                    'collection_id' => $collection->collection_id,
                ],
            ], array_merge($this->stripe->stripe_connect_auth, ['idempotency_key' => "in_terminal_{$collection->company_id}_{$collection->invoice_id}_{$collection->collection_id}"]));
        } catch (ApiConnectionException $e) {
            throw $e; // Stripe may have created it: resuming with the same key finds out
        } catch (ApiErrorException $e) {
            $collection->update(['status_id' => TerminalPaymentIntent::STATUS_FAILED]);

            throw $e;
        }

        $collection->update([
            'payment_intent_id' => $pi->id,
            'status_id' => TerminalPaymentIntent::STATUS_OPEN,
            'livemode' => $pi->livemode,
            'touched_at' => now(),
        ]);

        return $this->intentResponse($collection, $pi);
    }

    /* One collection per invoice: a charged one is recorded, a live one refuses, a stale one is cancelled */
    private function settleOpenCollections(Invoice $invoice): void
    {
        $collections = TerminalPaymentIntent::query()
            ->where('company_id', $invoice->company_id)
            ->where('invoice_id', $invoice->id)
            ->whereIn('status_id', TerminalPaymentIntent::OPEN_STATUSES)
            ->get();

        foreach ($collections as $collection) {
            /* Stripe never answered its create: after two minutes it isn't coming */
            if (!$collection->payment_intent_id && $collection->created_at->lt(now()->subMinutes(2))) {
                $collection->update(['status_id' => TerminalPaymentIntent::STATUS_FAILED]);

                continue;
            }

            try {
                $result = $collection->payment_intent_id ? $collection->cardPresent()->record($collection) : ['status' => 'creating'];
            } catch (ApiErrorException $e) {
                throw $this->collectionOpen($collection);
            }

            if (in_array($result['status'], ['recorded', 'unapplied', 'recording', 'processing'])) {
                throw new TerminalException('already_paid');
            }

            if ($collection->fresh()->isFinal()) {
                continue;
            }

            if (!$collection->isStale()) {
                throw $this->collectionOpen($collection);
            }

            try {
                $cancelled = $collection->cardPresent()->cancel($collection->fresh())['status'] == 'canceled';
            } catch (ApiErrorException $e) {
                $cancelled = false;
            }

            if (!$cancelled) {
                throw $this->collectionOpen($collection);
            }
        }
    }

    private function collectionOpen(TerminalPaymentIntent $collection): TerminalException
    {
        return new TerminalException('collection_open', 409, [
            'payment_intent_id' => $collection->payment_intent_id,
            'user' => $collection->user?->present()->name(),
            'age' => (int) $collection->created_at->diffInSeconds(now(), true),
        ]);
    }

    private function validateAmount(Invoice $invoice, float $amount): void
    {
        if ($invoice->is_deleted || $invoice->is_proforma || in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED, Invoice::STATUS_PAID])) {
            throw ValidationException::withMessages(['invoice' => ctrans('texts.terminal_not_payable')]);
        }

        $payable = $invoice->status_id == Invoice::STATUS_DRAFT ? $invoice->amount : $invoice->balance;
        $fees_and_limits = $this->stripe->company_gateway->getFeesAndLimits(GatewayType::CARD_PRESENT);
        $min = floatval($fees_and_limits->min_limit ?? -1);
        $max = floatval($fees_and_limits->max_limit ?? -1);

        if ($this->toStripe($invoice, $amount) <= 0 || $this->toStripe($invoice, $amount) > $this->toStripe($invoice, $payable)
            || ($min > 0 && $amount < $min) || ($max > 0 && $amount > $max)) {
            throw ValidationException::withMessages(['amount' => ctrans('texts.terminal_invalid_amount')]);
        }
    }

    /* The device confirms the amount we answer: a PaymentIntent left at another one, by an update whose response was lost, is set back (true) and the card read again */
    private function resetAmount(TerminalPaymentIntent $collection, PaymentIntent $pi, float $surcharge): bool
    {
        $amount = $this->toStripe($collection->invoice, $collection->amount + $surcharge);

        if ($pi->amount == $amount) {
            return false;
        }

        PaymentIntent::update($pi->id, ['amount' => $amount, 'description' => $this->description($collection->invoice, $surcharge)], $this->stripe->stripe_connect_auth);

        return true;
    }

    private function setSurcharge(TerminalPaymentIntent $collection, string $path, float $fee): void
    {
        $payment_hash = $collection->payment_hash;

        $data = $payment_hash->data;
        $data->fee_net = $fee > 0 ? $collection->fee_net : 0;
        $data->amount_with_fee = $collection->amount + $fee;

        $payment_hash->data = $data;
        $payment_hash->fee_total = $fee;
        $payment_hash->save();

        $collection->update(['surcharge_path' => $path, 'touched_at' => now()]);
    }

    private function description(Invoice $invoice, ?float $surcharge = null): string
    {
        $description = ctrans('texts.invoice') . ' ' . $invoice->number;

        return $surcharge ? $description . ' (' . ctrans('texts.credit_card_surcharge') . ' ' . Number::formatMoney($surcharge, $invoice->client) . ')' : $description;
    }

    private function cacheKey(string $name): string
    {
        $gateway = $this->stripe->company_gateway;

        return 'terminal_' . $name . ':' . config('database.default') . ':' . $gateway->id . ':' . md5($gateway->getConfigField('account_id') . $gateway->getConfigField('apiKey'));
    }

    private function intentResponse(TerminalPaymentIntent $collection, PaymentIntent $pi): array
    {
        return [
            'collection_id' => $collection->collection_id,
            'payment_intent_id' => $pi->id,
            'client_secret' => $pi->client_secret,
            'amount' => $collection->amount,
            'fee_gross' => $collection->fee_gross,
            'total' => $collection->amount + $collection->fee_gross,
            'currency' => $collection->currency,
            'livemode' => $collection->livemode,
        ];
    }

    private function finalizeResponse(TerminalPaymentIntent $collection, bool $recollect, ?string $funding = null): array
    {
        $collection->refresh();

        $surcharge = $collection->surcharge_path == TerminalPaymentIntent::SURCHARGE_PATH_RECOLLECT ? $collection->fee_gross : 0;

        return [
            'path' => $collection->surcharge_path,
            'recollect' => $recollect,
            'funding' => $funding,
            'amount' => $collection->amount + $surcharge,
            'amount_minor' => $this->toStripe($collection->invoice, $collection->amount + $surcharge),
            'surcharge' => $surcharge,
        ];
    }
}
