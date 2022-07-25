<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Request;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Mail\Gateways\ACHVerificationNotification;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

class ACSS
{
    /** @var StripePaymentDriver */
    public StripePaymentDriver $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
        $this->stripe->init();
    }

    public function authorizeView($data)
    {
        $data['gateway'] = $this->stripe;

        return render('gateways.stripe.acss.authorize', array_merge($data));
    }

    public function authorizeResponse(Request $request)
    {
        $stripe_response = json_decode($request->input('gateway_response'));

        $customer = $this->stripe->findOrCreateCustomer();

        try {
            $source = Customer::createSource($customer->id, ['source' => $stripe_response->token->id], $this->stripe->stripe_connect_auth);
        } catch (InvalidRequestException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        $client_gateway_token = $this->storePaymentMethod($source, $request->input('method'), $customer);

        $verification = route('client.payment_methods.verification', ['payment_method' => $client_gateway_token->hashed_id, 'method' => GatewayType::ACSS], false);

        $mailer = new NinjaMailerObject();

        $mailer->mailable = new ACHVerificationNotification(
            auth()->guard('contact')->user()->client->company,
            route('client.contact_login', ['contact_key' => auth()->guard('contact')->user()->contact_key, 'next' => $verification])
        );

        $mailer->company = auth()->guard('contact')->user()->client->company;
        $mailer->settings = auth()->guard('contact')->user()->client->company->settings;
        $mailer->to_user = auth()->guard('contact')->user();

        NinjaMailerJob::dispatch($mailer);

        return redirect()->route('client.payment_methods.verification', ['payment_method' => $client_gateway_token->hashed_id, 'method' => GatewayType::ACSS]);
    }

    public function verificationView(ClientGatewayToken $token)
    {
        if (isset($token->meta->state) && $token->meta->state === 'authorized') {
            return redirect()
                ->route('client.payment_methods.show', $token->hashed_id)
                ->with('message', __('texts.payment_method_verified'));
        }

        $data = [
            'token' => $token,
            'gateway' => $this->stripe,
        ];

        return render('gateways.stripe.acss.verify', $data);
    }

    public function processVerification(Request $request, ClientGatewayToken $token)
    {
        $request->validate([
            'transactions.*' => ['integer', 'min:1'],
        ]);

        if (isset($token->meta->state) && $token->meta->state === 'authorized') {
            return redirect()
                ->route('client.payment_methods.show', $token->hashed_id)
                ->with('message', __('texts.payment_method_verified'));
        }

        $bank_account = Customer::retrieveSource($request->customer, $request->source, [], $this->stripe->stripe_connect_auth);

        try {
            $bank_account->verify(['amounts' => request()->transactions]);

            $meta = $token->meta;
            $meta->state = 'authorized';
            $token->meta = $meta;
            $token->save();

            return redirect()
                ->route('client.payment_methods.show', $token->hashed_id)
                ->with('message', __('texts.payment_method_verified'));
        } catch (CardException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function paymentView(array $data)
    {
        $this->stripe->init();

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
            'description' => $this->stripe->decodeUnicodeString(ctrans('texts.invoices').': '.collect($data['invoices'])->pluck('invoice_number')),
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::ACSS,
            ],
            'payment_method_options' => [
                'acss_debit' => [
                    'mandate_options' => [
                        'payment_schedule' => 'combined',
                        'interval_description' => 'when any invoice becomes due',
                        'transaction_type' => 'personal', // TODO: check if is company or personal https://stripe.com/docs/payments/acss-debit
                    ],
                    'verification_method' => 'instant',
                ],
            ],
        ], $this->stripe->stripe_connect_auth);

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
            // $this->storePaymentMethod($gateway_response);
            return $this->processSuccessfulPayment($gateway_response->id);
        }

        return $this->processUnsuccessfulPayment();
    }

    public function processSuccessfulPayment(string $payment_intent): \Illuminate\Http\RedirectResponse
    {
        $data = [
            'payment_method' => $payment_intent,
            'payment_type' => PaymentType::ACSS,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent,
            'gateway_type_id' => GatewayType::ACSS,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', $payment->hashed_id);
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
            $payment_meta->brand = (string) $method->acss_debit->bank_name;
            $payment_meta->last4 = (string) $method->acss_debit->last4;
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
