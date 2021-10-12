<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;

class ACSS
{
    /** @var StripePaymentDriver */
    public StripePaymentDriver $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView($data)
    {
        return render('gateways.stripe.acss.authorize', $data);
    }

    public function paymentView(array $data)
    {
        $data['gateway'] = $this->stripe;
        $data['return_url'] = $this->buildReturnUrl();
        $data['stripe_amount'] = $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency());
        $data['client'] = $this->stripe->client;
        $data['customer'] = $this->stripe->findOrCreateCustomer()->id;
        $data['country'] = $this->stripe->client->country->iso_3166_2;

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $data['stripe_amount'],
            'currency' => $this->stripe->client->currency()->code,
            'setup_future_usage' => 'off_session',
            'payment_method_types' => ['acss_debit'],
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $this->stripe->decodeUnicodeString(ctrans('texts.invoices') . ': ' . collect($data['invoices'])->pluck('invoice_number')),
            'payment_method_options' => [
                'acss_debit' => [
                    'mandate_options' => [
                        'payment_schedule' => 'combined',
                        'interval_description' => 'when any invoice becomes due',
                        'transaction_type' => 'personal' // TODO: check if is company or personal https://stripe.com/docs/payments/acss-debit
                    ],
                    'currency' => $this->stripe->client->currency()->code,
                ]
            ]
        ]);

        $data['pi_client_secret'] = $intent->client_secret;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $data['stripe_amount']]);
        $this->stripe->payment_hash->save();

        return render('gateways.stripe.acss.pay', $data);
    }

    private function buildReturnUrl(): string
    {
        return route('client.payments.response', [
            'company_gateway_id' => $this->stripe->company_gateway->id,
            'payment_hash' => $this->stripe->payment_hash->hash,
            'payment_method_id' => GatewayType::ACSS,
        ]);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {

        $gateway_response = json_decode($request->gateway_response);

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $request->all());
        $this->stripe->payment_hash->save();

        if (property_exists($gateway_response, 'status') && $gateway_response->status == 'processing') {

            $this->stripe->init();
            $this->storePaymentMethod($gateway_response);

            return $this->processSuccessfulPayment($gateway_response->id);
        }

        return $this->processUnsuccessfulPayment();

    }

    public function processSuccessfulPayment(string $payment_intent)
    {
        /* @todo: https://github.com/invoiceninja/invoiceninja/pull/3789/files#r436175798 */

        $this->stripe->init();

        $data = [
            'payment_method' => $payment_intent,
            'payment_type' => PaymentType::ACSS,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent,
            'gateway_type_id' => GatewayType::ACSS,
        ];

        $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.index');
    }

    public function processUnsuccessfulPayment()
    {
        $server_response = $this->stripe->payment_hash->data;

        PaymentFailureMailer::dispatch(
            $this->stripe->client,
            $server_response,
            $this->stripe->client->company,
            $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency())
        );

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

    private function storePaymentMethod($intent)
    {
        try {

            $method = $this->stripe->getStripePaymentMethod($intent->payment_method);

            $payment_meta = new \stdClass;
            $payment_meta->brand = (string) \sprintf('%s (%s)', $method->au_becs_debit->bank_code, ctrans('texts.acss'));
            $payment_meta->last4 = (string) $method->au_becs_debit->last4;
            $payment_meta->state = 'authorized';
            $payment_meta->type = GatewayType::ACSS;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $intent->payment_method,
                'payment_method_id' => GatewayType::ACSS,
            ];

            $this->stripe->storeGatewayToken($data, ['gateway_customer_reference' => $method->customer]);
        } catch (\Exception $e) {
            return $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }
}
