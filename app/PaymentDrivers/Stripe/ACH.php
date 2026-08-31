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

use App\Exceptions\PaymentFailed;
use Illuminate\Http\Request;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\SystemLogger;
use App\Mail\Gateways\ACHVerificationNotification;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Traits\MakesHash;
use Exception;
use Illuminate\Http\RedirectResponse;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\RateLimitException;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;

class ACH implements LivewireMethodInterface
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * Authorize a bank account - requires microdeposit verification
     */
    // public function authorizeView(array $data)
    // {
    //     $data['gateway'] = $this->stripe;

    //     return render('gateways.stripe.ach.authorize', array_merge($data));
    // }


    /**
     * Instant Verification methods with fall back to microdeposits.
     *
     * @param array $data
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->stripe;

        $customer = $this->stripe->findOrCreateCustomer();

        // Create SetupIntent with Financial Connections for instant verification
        try {
            $intent = \Stripe\SetupIntent::create([
                'customer' => $customer->id,
                'usage' => 'off_session',
                'payment_method_types' => ['us_bank_account'],
                'payment_method_options' => [
                    'us_bank_account' => [
                        'financial_connections' => [
                            'permissions' => ['payment_method'],
                        ],
                        'verification_method' => 'automatic',
                    ],
                ],
            ], $this->stripe->stripe_connect_auth);
        } catch (InvalidRequestException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        $data['client_secret'] = $intent->client_secret;
        $data['customer'] = $customer;

        return render('gateways.stripe.ach.authorize', array_merge($data));
    }

    public function authorizeResponse($request)
    {
        $this->stripe->init();

        $setup_intent = json_decode($request->input('gateway_response'));

        if (!$setup_intent || !isset($setup_intent->payment_method)) {
            throw new PaymentFailed('Invalid response from payment gateway.');
        }

        $customer = $this->stripe->findOrCreateCustomer();

        try {
            // Retrieve the payment method to get bank account details
            $payment_method = $this->stripe->getStripePaymentMethod($setup_intent->payment_method);

            if (!$payment_method || !isset($payment_method->us_bank_account)) {
                throw new PaymentFailed('Unable to retrieve bank account details.');
            }

            $bank_account = $payment_method->us_bank_account;

            // Determine verification state based on SetupIntent status
            /** @var string $status */
            $status = $setup_intent->status ?? 'unauthorized'; //@phpstan-ignore-line
            $state = match ($status) {
                'succeeded' => 'authorized',
                'requires_action' => 'unauthorized', // Microdeposit verification pending
                default => 'unauthorized',
            };

            // Build a new stdClass object for storage (Stripe objects are immutable)
            $method = new \stdClass();
            $method->id = $setup_intent->payment_method; //@phpstan-ignore-line
            $method->bank_name = $bank_account->bank_name;
            $method->last4 = $bank_account->last4;
            $method->state = $state;

            // If microdeposit verification is required, store the verification URL
            if ($status === 'requires_action'
               && isset($setup_intent->next_action)
               && ($setup_intent->next_action->type ?? null) === 'verify_with_microdeposits') { //@phpstan-ignore-line
                $method->next_action = $setup_intent->next_action->verify_with_microdeposits->hosted_verification_url ?? null; //@phpstan-ignore-line
            }

            // Note: We don't attach the payment method here - it's already linked to the
            // customer via the SetupIntent. For us_bank_account, the payment method must be
            // verified before it can be used. Verification happens via:
            // - Instant verification (Financial Connections) - already verified
            // - Microdeposits - verified via webhook (setup_intent.succeeded)

            $client_gateway_token = $this->storePaymentMethod($method, GatewayType::BANK_TRANSFER, $customer);

            // If instant verification succeeded, redirect to payment methods
            if ($state === 'authorized') {
                return redirect()->route('client.payment_methods.show', ['payment_method' => $client_gateway_token->hashed_id])
                    ->with('message', ctrans('texts.payment_method_added'));
            }

            // If microdeposit verification required, send notification and redirect
            $verification = route('client.payment_methods.verification', [
                'payment_method' => $client_gateway_token->hashed_id,
                'method' => GatewayType::BANK_TRANSFER,
            ], false);

            $mailer = new NinjaMailerObject();

            $mailer->mailable = new ACHVerificationNotification(
                auth()->guard('contact')->user()->client->company,
                route('client.contact_login', [
                    'contact_key' => auth()->guard('contact')->user()->contact_key,
                    'next' => $verification,
                ])
            );

            $mailer->company = auth()->guard('contact')->user()->client->company;
            $mailer->settings = auth()->guard('contact')->user()->client->company->settings;
            $mailer->to_user = auth()->guard('contact')->user();

            NinjaMailerJob::dispatch($mailer);

            return redirect()->route('client.payment_methods.verification', [
                'payment_method' => $client_gateway_token->hashed_id,
                'method' => GatewayType::BANK_TRANSFER,
            ]);

        } catch (InvalidRequestException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Handle customer.source.updated webhook (legacy Sources API)
     */
    public function updateBankAccount(array $event)
    {
        $stripe_event = $event['data']['object'];

        $token = ClientGatewayToken::query()->where('token', $stripe_event['id'])
                                   ->where('gateway_customer_reference', $stripe_event['customer'])
                                   ->first();

        if (
            $token
            && ($stripe_event['object'] ?? null) === 'bank_account'
            && ($stripe_event['status'] ?? null) === 'verified'
            && ($token->meta->state ?? null) !== 'authorized'
        ) {
            $meta = $token->meta;
            $meta->state = 'inactive';
            $token->meta = $meta;
            $token->save();
        }
    }

    /**
     * Handle setup_intent.succeeded webhook (new SetupIntent/Financial Connections flow)
     *
     * This is called when microdeposit verification is completed for us_bank_account payment methods.
     */
    public function handleSetupIntentSucceeded(array $event): void
    {
        $setup_intent = $event['data']['object'];

        // Only handle us_bank_account payment methods
        if (!isset($setup_intent['payment_method']) || !isset($setup_intent['payment_method_types'])) {
            return;
        }

        if (!in_array('us_bank_account', $setup_intent['payment_method_types'])) {
            return;
        }

        $payment_method_id = $setup_intent['payment_method'];
        $customer_id = $setup_intent['customer'] ?? null;

        if (!$payment_method_id || !$customer_id) {
            return;
        }

        // Find the token by payment method ID
        $token = ClientGatewayToken::query()
            ->where('token', $payment_method_id)
            ->where('gateway_customer_reference', $customer_id)
            ->first();

        if (!$token) {
            nlog("ACH SetupIntent succeeded but no matching token found for payment_method: {$payment_method_id}");
            return;
        }

        // Update the token state to authorized
        $meta = $token->meta;
        $meta->state = 'authorized';

        // Clear the next_action since verification is complete
        if (isset($meta->next_action)) {
            unset($meta->next_action);
        }

        $token->meta = $meta;
        $token->save();

        nlog("ACH bank account verified via SetupIntent webhook: {$payment_method_id}");
    }

    public function handleSetupIntentFailed(array $event): void
    {
        $setup_intent = $event['data']['object'];

        if (!in_array('us_bank_account', $setup_intent['payment_method_types'] ?? [], true)) {
            return;
        }

        $payment_method_id = $setup_intent['payment_method']
            ?? $setup_intent['last_setup_error']['payment_method']['id']
            ?? null;
        $customer_id = $setup_intent['customer'] ?? null;

        if (!$payment_method_id || !$customer_id) {
            return;
        }

        $token = ClientGatewayToken::query()
            ->where('token', $payment_method_id)
            ->where('gateway_customer_reference', $customer_id)
            ->first();

        if (!$token || !in_array($token->meta->state ?? null, ['unauthorized', 'pending'], true)) {
            return;
        }

        $meta = $token->meta;
        $meta->state = 'unauthorized';
        unset($meta->next_action);
        $token->meta = $meta;
        $token->save();
    }

    public function handleMandateUpdated(array $event): void
    {
        $mandate = $event['data']['object'] ?? [];
        $status = $mandate['status'] ?? null;
        $payment_method_id = $mandate['payment_method'] ?? null;

        if (
            ! in_array($status, ['active', 'inactive'], true)
            || ! is_string($payment_method_id)
            || $payment_method_id === ''
        ) {
            return;
        }

        $token = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->stripe->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->where('token', $payment_method_id)
            ->first();

        if (! $token) {
            return;
        }

        $meta = $token->meta;
        $state = $meta->state ?? null;
        $next_state = match (true) {
            $status === 'active' && $state === 'inactive' => 'authorized',
            $status === 'inactive' && $state === 'authorized' => 'inactive',
            default => null,
        };

        if ($next_state === null) {
            return;
        }

        $meta->state = $next_state;

        if ($next_state === 'authorized') {
            unset($meta->next_action);
        }

        $token->meta = $meta;
        $token->save();
    }

    public function verificationView(ClientGatewayToken $token)
    {
        $this->stripe->init();

        $state = $token->meta->state ?? null;
        $next_action = $token->meta->next_action ?? null;

        if (
            in_array($state, ['unauthorized', 'pending'], true)
            && is_string($next_action)
            && $next_action !== ''
        ) {
            return redirect($next_action);
        }

        $payment_method = $this->stripe->getStripePaymentMethod($token->token);

        if (in_array($state, ['authorized', 'inactive'], true) && !$payment_method->customer) {
            $meta = $token->meta;
            $meta->state = 'unauthorized';
            unset($meta->next_action);
            $token->meta = $meta;
            $token->save();

            return redirect()->route('client.payment_methods.show', $token->hashed_id);
        }

        if ($state === 'authorized') {
            return redirect()
                ->route('client.payment_methods.show', $token->hashed_id)
                ->with('message', __('texts.payment_method_verified'));
        }

        if ($state === 'inactive') {
            return $this->mandateReauthorizationView($token);
        }

        if (str_starts_with($token->token, 'pm_')) {
            return redirect()->route('client.payment_methods.show', $token->hashed_id);
        }

        $bank_account = Customer::retrieveSource($token->gateway_customer_reference, $token->token, [], $this->stripe->stripe_connect_auth);

        /* A verified legacy source still needs a reusable mandate. */
        if (isset($bank_account->status) && $bank_account->status == 'verified') {
            $meta = $token->meta;
            $meta->state = 'inactive';
            $token->meta = $meta;
            $token->save();

            return $this->mandateReauthorizationView($token);
        }

        $data = [
            'token' => $token,
            'gateway' => $this->stripe,
        ];

        return render('gateways.stripe.ach.verify', $data);
    }

    public function processVerification(Request $request, ClientGatewayToken $token)
    {
        if (isset($token->meta->state) && $token->meta->state === 'authorized') {
            return redirect()
                ->route('client.payment_methods.show', $token->hashed_id)
                ->with('message', __('texts.payment_method_verified'));
        }

        if (isset($token->meta->state) && $token->meta->state === 'inactive') {
            return $this->processMandateReauthorization($request, $token);
        }

        if (str_starts_with($token->token, 'pm_')) {
            return redirect()
                ->route('client.payment_methods.create', ['method' => GatewayType::BANK_TRANSFER])
                ->with('error', __('texts.unable_to_verify_payment_method'));
        }

        $this->stripe->init();

        $bank_account = Customer::retrieveSource($request->customer, $request->source, [], $this->stripe->stripe_connect_auth);

        try {
            $bank_account->verify(['amounts' => $request->input('transactions')]);

            $meta = $token->meta;
            $meta->state = 'inactive';
            $token->meta = $meta;
            $token->save();

            return redirect()
                ->route('client.payment_methods.verification', [
                    'payment_method' => $token->hashed_id,
                    'method' => GatewayType::BANK_TRANSFER,
                ]);
        } catch (CardException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function mandateReauthorizationView(ClientGatewayToken $token)
    {
        if ($this->stripe->hasCompleteBillingAddress()) {
            $this->stripe->syncAchPaymentMethodBillingAddress($token);
        }

        $intent = $this->stripe->createSetupIntent([
            'customer' => $token->gateway_customer_reference,
            'payment_method' => $token->token,
            'payment_method_types' => ['us_bank_account'],
            'usage' => 'off_session',
        ]);

        $this->storeExpectedMandateSetupIntent($token, $intent);

        return render('gateways.stripe.ach.reauthorize', [
            'client_secret' => $intent->client_secret,
            'gateway' => $this->stripe,
            'token' => $token,
        ]);
    }

    private function processMandateReauthorization(Request $request, ClientGatewayToken $token): RedirectResponse
    {
        $this->validatedMandateSetupIntent($request, $token);
        $this->stripe->syncAchPaymentMethodBillingAddress($token);

        $meta = $token->meta;
        $meta->state = 'authorized';
        unset($meta->next_action);
        $token->meta = $meta;
        $token->save();

        return redirect()
            ->route('client.payment_methods.show', $token->hashed_id)
            ->with('message', __('texts.payment_method_verified'));
    }

    /**
     * Make a payment WITH instant verification.
     */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        if (!$data['authorized']) {
            $token = $data['tokens'][0];
            return redirect()->route('client.payment_methods.show', $token->hashed_id);
        }

        return render('gateways.stripe.ach.pay', $data);
    }

    /**
     * tokenBilling
     *
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $description = $this->stripe->getDescription(false);

        return $this->paymentIntentTokenBilling($amount, $description, $cgt, false);

    }

    public function paymentIntentTokenBilling($amount, $description, $cgt, $client_present = true, ?string $mandate = null)
    {
        $this->stripe->init();

        $response = false;
        try {
            $data = [
                'amount' => $this->stripe->convertToStripeAmount($amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
                'currency' => $this->stripe->client->getCurrencyCode(),
                'payment_method' => $cgt->token,
                'customer' => $cgt->gateway_customer_reference,
                'confirm' => true,
                'description' => $description,
                'off_session' => true,
                'metadata' => [
                    'payment_hash' => $this->stripe->payment_hash->hash,
                    'gateway_type_id' => $cgt->gateway_type_id,
                ],
                'statement_descriptor' => $this->stripe->getStatementDescriptor(),

            ];

            if ($cgt->gateway_type_id == GatewayType::BANK_TRANSFER) {
                $data['payment_method_types'] = ['us_bank_account'];
            }

            if ($mandate) {
                $data['mandate'] = $mandate;
            }

            $response = $this->stripe->createPaymentIntent($data);

            SystemLogger::dispatch($response, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->stripe->client, $this->stripe->client->company);
        } catch (\Exception $e) {
            $data = [
                'status' => '',
                'error_type' => '',
                'error_code' => '',
                'param' => '',
                'message' => '',
            ];

            switch ($e) {
                case $e instanceof CardException:
                    /** @var CardException $e */
                    $data['status'] = $e->getHttpStatus();
                    $data['error_type'] = $e->getError()->type;
                    $data['error_code'] = $e->getError()->code;
                    $data['param'] = $e->getError()->param;
                    $data['message'] = $e->getError()->message;
                    break;
                case $e instanceof RateLimitException:
                    $data['message'] = 'Too many requests made to the API too quickly';
                    break;
                case $e instanceof InvalidRequestException:

                    if ($client_present) {
                        return redirect()->route('client.payment_methods.verification', ['payment_method' => $cgt->hashed_id, 'method' => GatewayType::BANK_TRANSFER]);
                    }

                    $data['message'] = $e->getMessage();
                    break;

                case $e instanceof AuthenticationException:
                    $data['message'] = 'Authentication with Stripe\'s API failed';
                    break;
                case $e instanceof ApiErrorException:
                    $data['message'] = 'Network communication with Stripe failed';
                    break;

                default:
                    $data['message'] = $e->getMessage();
                    break;
            }

            $this->stripe->processInternallyFailedPayment($this->stripe, $e);

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client, $this->stripe->client->company);
        }

        if (! $response) {
            return false;
        }

        $payment_method_type = PaymentType::ACH;

        $data = [
            'gateway_type_id' => $cgt->gateway_type_id,
            'payment_type' => PaymentType::ACH,
            'transaction_reference' => $response->latest_charge ?? $response->charges->data[0]->id,
            'amount' => $amount,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);
        $payment->meta = $cgt->meta;
        $payment->save();

        $this->stripe->payment_hash->payment_id = $payment->id;
        $this->stripe->payment_hash->save();

        if ($client_present) {
            return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
        }

        return $payment;
    }

    public function handlePaymentIntentResponse($request)
    {
        $response = json_decode($request->gateway_response);
        $bank_account_response = json_decode($request->bank_account_response);

        if (in_array($response->status, ['requires_action','requires_source_action']) && ($response->next_action->type ?? null) == 'verify_with_microdeposits') {
            $method = $bank_account_response->payment_method->us_bank_account ?? null;

            if (!$method) {
                throw new PaymentFailed('Unable to retrieve bank account details');
            }

            $method->id = $response->payment_method;
            $method->state = 'unauthorized';
            $method->next_action = $response->next_action->verify_with_microdeposits->hosted_verification_url ?? null;

            $customer = $this->stripe->getCustomer($request->customer);
            $cgt = $this->storePaymentMethod($method, GatewayType::BANK_TRANSFER, $customer);

            return redirect()->route('client.payment_methods.show', ['payment_method' => $cgt->hashed_id]);
        }

        $method = $bank_account_response->payment_method->us_bank_account;
        $method->id = $response->payment_method;
        $method->state = 'authorized';

        $this->stripe->payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->first();

        if ($response->id && $response->status === 'processing') {
            $payment_intent = PaymentIntent::retrieve($response->id, $this->stripe->stripe_connect_auth);

            $state = [
                'gateway_type_id' => GatewayType::BANK_TRANSFER,
                'amount' => $response->amount,
                'currency' => $response->currency,
                'customer' => $request->customer,
                'source' => $response->payment_method,
                'charge' => $response,
            ];

            $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
            $this->stripe->payment_hash->save();

            $customer = $this->stripe->getCustomer($request->customer);

            $this->storePaymentMethod($method, GatewayType::BANK_TRANSFER, $customer);

            return $this->processPendingPayment($state, true);
        }

        if ($response->next_action) {
        }
    }

    public function processPendingPaymentIntent($state, $client_present = true)
    {
        $this->stripe->init();

        $data = [
            'payment_method' => $state['source'],
            'payment_type' => PaymentType::ACH,
            'amount' => $state['amount'],
            'transaction_reference' => $state['charge'],
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $state, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        if (! $client_present) {
            return $payment;
        }

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function paymentResponse($request)
    {
        $this->stripe->init();

        //it may be a payment intent here.
        if ($request->input('client_secret') != '') {
            return $this->handlePaymentIntentResponse($request);
        }

        $source = ClientGatewayToken::query()
            ->where('id', $this->decodePrimaryKey($request->source))
            ->where('client_id', $this->stripe->client->id)
            ->firstOrFail();

        $state = [
            'payment_method' => $request->payment_method_id,
            'gateway_type_id' => $request->company_gateway_id,
            'amount' => $this->stripe->convertToStripeAmount($request->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $request->currency,
            'customer' => $request->customer,
        ];

        $state = array_merge($state, $request->all());
        $state['source'] = $source->token;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
        $this->stripe->payment_hash->save();

        $amount = array_sum(array_column($this->stripe->payment_hash->invoices(), 'amount')) + $this->stripe->payment_hash->fee_total;

        $description = $this->stripe->getDescription(false);

        $this->stripe->syncAchPaymentMethodBillingAddress($source);

        $state = $source->meta->state ?? null;

        if ($state === 'inactive' && ! $request->filled('setup_intent_id')) {
            throw new PaymentFailed('ACH authorization is required before making this payment.', 400);
        }

        if ($request->filled('setup_intent_id')) {
            $setup_intent = $this->validatedMandateSetupIntent($request, $source);

            $meta = $source->meta;
            $meta->state = 'authorized';
            unset($meta->next_action);
            $source->meta = $meta;
            $source->save();

            return $this->paymentIntentTokenBilling(
                $amount,
                $description,
                $source,
                true,
                (string) $setup_intent->mandate,
            );
        }

        // if (substr($source->token, 0, 2) === 'pm') {
        return $this->paymentIntentTokenBilling($amount, $description, $source);
        // }

        try {
            $state['charge'] = \Stripe\Charge::create([
                'amount' => $state['amount'],
                'currency' => $state['currency'],
                'customer' => $state['customer'],
                'source' => $state['source'],
                'description' => $description,
            ], $this->stripe->stripe_connect_auth);

            $state = array_merge($state, $request->all());

            $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
            $this->stripe->payment_hash->save();

            if ($state['charge']->status === 'pending' && is_null($state['charge']->failure_message)) {
                return $this->processPendingPayment($state);
            }

            return $this->processUnsuccessfulPayment($state);
        } catch (Exception $e) {
            if ($e instanceof CardException) {
                return redirect()->route('client.payment_methods.verification', ['payment_method' => $source->hashed_id, 'method' => GatewayType::BANK_TRANSFER]);
            }

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    public function processPendingPayment($state, $client_present = true)
    {
        $this->stripe->init();

        $data = [
            'payment_method' => $state['source'],
            'payment_type' => PaymentType::ACH,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $state['charge']->id,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $state['charge'], 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        if (! $client_present) {
            return $payment;
        }

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($state)
    {
        $this->stripe->sendFailureMail($state['charge']);

        $message = [
            'server_response' => $state['charge'],
            'data' => $this->stripe->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    private function storePaymentMethod($method, $payment_method_id, $customer)
    {
        $state = property_exists($method, 'state') ? $method->state : 'unauthorized';

        try {
            $payment_meta = new \stdClass();
            $payment_meta->brand = (string) \sprintf('%s (%s)', $method->bank_name, ctrans('texts.ach'));
            $payment_meta->last4 = (string) $method->last4;
            $payment_meta->type = GatewayType::BANK_TRANSFER;
            $payment_meta->state = $state;

            if (property_exists($method, 'next_action')) {
                $payment_meta->next_action = $method->next_action;
            }

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->id,
                'payment_method_id' => $payment_method_id,
            ];

            /**
             * Ensure the method does not already exist!!
             */

            $token = ClientGatewayToken::where([
                'gateway_customer_reference' => $customer->id,
                'token' => $method->id,
                'client_id' => $this->stripe->client->id,
                'company_id' => $this->stripe->client->company_id,
            ])->first();

            if ($token) {
                return $token;
            }

            return $this->stripe->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);
        } catch (Exception $e) {
            return $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.stripe.ach.pay_livewire';
    }

    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->stripe;
        $data['currency'] = $this->stripe->client->getCurrencyCode();
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['customer'] = $this->stripe->findOrCreateCustomer();
        $data['amount'] = $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency());
        $data['authorized'] = true;
        $data['mandate_client_secret'] = false;

        $description = $this->stripe->getDescription(false);

        $intent = false;

        if (count($data['tokens']) == 1) {

            $token = $data['tokens'][0];

            $meta = $token->meta;

            if (isset($meta->state) && $meta->state == 'unauthorized') {
                $data['authorized'] = false;
                // return redirect()->route('client.payment_methods.show', $token->hashed_id);
            }
        }

        if (count($data['tokens']) == 0) {
            $intent
            = $this->stripe->createPaymentIntent(
                [
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'setup_future_usage' => 'off_session',
                    'customer' => $data['customer']->id,
                    'payment_method_types' => ['us_bank_account'],
                    'description' => $description,
                    'metadata' => [
                        'payment_hash' => $this->stripe->payment_hash->hash,
                        'gateway_type_id' => GatewayType::BANK_TRANSFER,
                    ],
                    'statement_descriptor' => $this->stripe->getStatementDescriptor(),
                ]
            );
        }

        $inactive_tokens = collect($data['tokens'])
            ->filter(fn(ClientGatewayToken $token): bool => ($token->meta->state ?? null) === 'inactive');

        if ($inactive_tokens->isNotEmpty()) {
            if ($this->stripe->hasCompleteBillingAddress()) {
                $inactive_tokens->each(
                    fn(ClientGatewayToken $token) => $this->stripe->syncAchPaymentMethodBillingAddress($token)
                );
            }

            $setup_intent = $this->stripe->createSetupIntent([
                'customer' => $data['customer']->id,
                'payment_method_types' => ['us_bank_account'],
                'usage' => 'off_session',
            ]);

            $inactive_tokens->each(
                fn(ClientGatewayToken $token) => $this->storeExpectedMandateSetupIntent($token, $setup_intent)
            );

            $data['mandate_client_secret'] = $setup_intent->client_secret;
        }

        $data['client_secret'] = $intent ? $intent->client_secret : false;

        return $data;
    }

    private function validatedMandateSetupIntent(Request $request, ClientGatewayToken $token): SetupIntent
    {
        $setup_intent_id = (string) $request->input('setup_intent_id');
        $expected_setup_intent_id = $request->session()->get($this->mandateSetupIntentSessionKey($token));

        if (! is_string($expected_setup_intent_id) || ! hash_equals($expected_setup_intent_id, $setup_intent_id)) {
            throw new PaymentFailed('Unable to renew the ACH authorization.', 400);
        }

        $setup_intent = $this->stripe->getSetupIntentId($setup_intent_id);

        if (
            $setup_intent->status !== 'succeeded'
            || $setup_intent->customer !== $token->gateway_customer_reference
            || $setup_intent->payment_method !== $token->token
            || ! is_string($setup_intent->mandate)
        ) {
            throw new PaymentFailed('Unable to renew the ACH authorization.', 400);
        }

        $mandate = $this->stripe->getMandate($setup_intent->mandate);

        if ($mandate->status !== 'active' || $mandate->payment_method !== $token->token) {
            throw new PaymentFailed('Unable to renew the ACH authorization.', 400);
        }

        $request->session()->forget($this->mandateSetupIntentSessionKey($token));

        return $setup_intent;
    }

    private function storeExpectedMandateSetupIntent(ClientGatewayToken $token, SetupIntent $setup_intent): void
    {
        session()->put($this->mandateSetupIntentSessionKey($token), $setup_intent->id);
    }

    private function mandateSetupIntentSessionKey(ClientGatewayToken $token): string
    {
        return "stripe_ach.mandate_setup_intent.{$token->id}";
    }
}
