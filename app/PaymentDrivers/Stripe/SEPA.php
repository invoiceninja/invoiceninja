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

use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use Stripe\PaymentMethod;
use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\Payment;
use Stripe\PaymentIntent;

class SEPA
{
    /** @var StripePaymentDriver */
    public StripePaymentDriver $stripe;

    public function __construct(StripePaymentDriver $stripe_driver)
    {
        $this->stripe_driver = $stripe_driver;
    }

    public function authorizeView(array $data)
    {
        $customer = $this->stripe_driver->findOrCreateCustomer();

        $setup_intent = \Stripe\SetupIntent::create([
          'payment_method_types' => ['sepa_debit'],
          'customer' => $customer->id,
        ], $this->stripe_driver->stripe_connect_auth);

        $client_secret = $setup_intent->client_secret;
        // Pass the client secret to the client

        $data['gateway'] = $this->stripe;

        return render('gateways.stripe.sepa.authorize', array_merge($data));
    }

    public function paymentView(array $data)
    {
        // TODO: implement paymentView
    }

    public function paymentResponse($request)
    {
        $state = [
            'server_response' => json_decode($request->gateway_response),
            'payment_hash' => $request->payment_hash,
        ];

        $state['payment_intent'] = \Stripe\PaymentIntent::retrieve($state['server_response']->id, $this->stripe_driver->stripe_connect_auth);

        $state['customer'] = $state['payment_intent']->customer;

        $this->stripe_driver->payment_hash->data = array_merge((array) $this->stripe_driver->payment_hash->data, $state);
        $this->stripe_driver->payment_hash->save();

        $server_response = $this->stripe_driver->payment_hash->data->server_response;

        $response_handler = new SEPA($this->stripe_driver);

        if ($server_response->status == 'succeeded') {

            $this->stripe_driver->logSuccessfulGatewayResponse(['response' => json_decode($request->gateway_response), 'data' => $this->stripe_driver->payment_hash], SystemLog::TYPE_STRIPE);

            return $response_handler->processSuccessfulPayment($state);
        }

        return $response_handler->processUnsuccessfulPayment($state);
    }
    public function processSuccessfulPayment($server_response)
    {
        $stripe_method = $this->stripe->getStripePaymentMethod($this->stripe->payment_hash->data->server_response->payment_method);

        $data = [
            'payment_method' => $this->stripe->payment_hash->data->server_response->payment_method,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->server_response->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => optional($this->stripe->payment_hash->data->payment_intent->charges->data[0])->id,
            'gateway_type_id' => GatewayType::SEPA,
        ];

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['amount' => $data['amount']]);
        $this->stripe->payment_hash->save();

               $payment = $this->stripe->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data->server_response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfullyPayment($server_response)
    {
        PaymentFailureMailer::dispatch($this->stripe->client, $server_response->cancellation_reason, $this->stripe->client->company, $server_response->amount);

        PaymentFailureMailer::dispatch(
            $this->stripe->client,
            $server_response,
            $this->stripe->client->company,
            $server_response->amount
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

    private function storePaymentMethod(PaymentMethod $method, $payment_method_id, $customer)
    {
        // TODO: implement storePaymentMethod
    }
}

