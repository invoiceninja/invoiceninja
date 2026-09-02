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
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\StripePaymentDriver;
use Stripe\PaymentIntent;

class PromptPay implements LivewireMethodInterface
{
    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * PromptPay is a single-use payment method; Stripe does not support
     * SetupIntents / tokenization for it.
     *
     * @param array $data
     * @return never
     * @throws PaymentFailed
     */
    public function authorizeView(array $data)
    {
        throw new PaymentFailed('PromptPay does not support payment method tokenization');
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return never
     * @throws PaymentFailed
     */
    public function authorizeResponse($request)
    {
        throw new PaymentFailed('PromptPay does not support payment method tokenization');
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.stripe.promptpay.pay', $data);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.stripe.promptpay.pay_livewire';
    }

    public function paymentData(array $data): array
    {
        $this->stripe->init();

        $stripe_amount = $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency());

        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $stripe_amount,
                'currency' => strtolower($this->stripe->client->currency()->code),
                'payment_method_types' => ['promptpay'],
                'customer' => $this->stripe->findOrCreateCustomer()->id,
                'description' => $this->stripe->getDescription(false),
                'metadata' => [
                    'payment_hash' => $this->stripe->payment_hash->hash,
                    'gateway_type_id' => GatewayType::PROMPTPAY,
                ],
            ], array_merge($this->stripe->stripe_connect_auth, ['idempotency_key' => uniqid("st", true)]));
        } catch (\Throwable $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $stripe_amount]);
        $this->stripe->payment_hash->save();

        $data['gateway'] = $this->stripe;
        $data['intent'] = $intent;
        $data['client_secret'] = $intent->client_secret;

        return $data;
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->stripe->init();

        $server_response = json_decode($request->gateway_response);

        $state = [
            'server_response' => $server_response,
            'payment_hash' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());

        $state['payment_intent'] = PaymentIntent::retrieve($server_response->id, array_merge($this->stripe->stripe_connect_auth, ['idempotency_key' => uniqid("st", true)]));

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
        $this->stripe->payment_hash->save();

        $payment_intent = $this->stripe->payment_hash->data->payment_intent;//@phpstan-ignore-line

        $references = array_values(array_filter([$payment_intent->id, $payment_intent->latest_charge ?? null], fn ($v) => is_string($v) && $v !== ''));

        /* The webhook may have already settled this payment in parallel. */
        if (count($references) > 0) {
            $existing = Payment::query()
                ->where('company_id', $this->stripe->client->company_id)
                ->whereIn('transaction_reference', $references)
                ->first();

            if ($existing) {
                return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($existing->id)]);
            }
        }

        if (in_array($payment_intent->status, ['succeeded', 'processing'])) {
            return $this->processSuccessfulPayment($payment_intent);
        }

        return $this->processUnsuccesfulRedirect($payment_intent);
    }

    /**
     * @param PaymentIntent $payment_intent
     * @return \Illuminate\Http\RedirectResponse
     */
    private function processSuccessfulPayment(PaymentIntent $payment_intent)
    {
        $data = [
            'payment_method' => $payment_intent->payment_method,
            'payment_type' => PaymentType::PROMPTPAY,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent->latest_charge ?: $payment_intent->id,
            'gateway_type_id' => GatewayType::PROMPTPAY,
        ];

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['amount' => $data['amount']]);
        $this->stripe->payment_hash->save();

        $payment = $this->stripe->createPayment($data, $payment_intent->status == 'succeeded' ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data->server_response, 'data' => $data],//@phpstan-ignore-line
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    /**
     * @param PaymentIntent $payment_intent
     * @return never
     * @throws PaymentFailed
     */
    private function processUnsuccesfulRedirect(PaymentIntent $payment_intent)
    {
        $server_response = $this->stripe->payment_hash->data;

        $this->stripe->sendFailureMail($payment_intent->status ?? 'failed');

        $message = [
            'server_response' => $server_response,
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
}
