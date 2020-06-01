<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\Stripe;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;

class CreditCard
{
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView(array $data)
    {
        $intent['intent'] = $this->stripe->getSetupIntent();

        return render('gateways.stripe.add_credit_card', array_merge($data, $intent));
    }

    public function authorizeResponse($request)
    {
        $server_response = json_decode($request->input('gateway_response'));

        $gateway_id = $request->input('gateway_id');
        $gateway_type_id = $request->input('gateway_type_id');
        $is_default = $request->input('is_default');

        $payment_method = $server_response->payment_method;

        $customer = $this->stripe->findOrCreateCustomer();

        $this->stripe->init();

        $stripe_payment_method = \Stripe\PaymentMethod::retrieve($payment_method);
        $stripe_payment_method_obj = $stripe_payment_method->jsonSerialize();
        $stripe_payment_method->attach(['customer' => $customer->id]);

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = $stripe_payment_method_obj['card']['exp_month'];
        $payment_meta->exp_year = $stripe_payment_method_obj['card']['exp_year'];
        $payment_meta->brand = $stripe_payment_method_obj['card']['brand'];
        $payment_meta->last4 = $stripe_payment_method_obj['card']['last4'];
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $client_gateway_token = new ClientGatewayToken();
        $client_gateway_token->company_id = $this->stripe->client->company->id;
        $client_gateway_token->client_id = $this->stripe->client->id;
        $client_gateway_token->token = $payment_method;
        $client_gateway_token->company_gateway_id = $this->stripe->company_gateway->id;
        $client_gateway_token->gateway_type_id = $gateway_type_id;
        $client_gateway_token->gateway_customer_reference = $customer->id;
        $client_gateway_token->meta = $payment_meta;
        $client_gateway_token->save();

        if ($is_default == 'true' || $this->stripe->client->gateway_tokens->count() == 1) {
            $this->stripe->client->gateway_tokens()->update(['is_default' => 0]);

            $client_gateway_token->is_default = 1;
            $client_gateway_token->save();
        }

        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data)
    {
        $payment_intent_data = [
            'amount' => $this->stripe->convertToStripeAmount($data['amount_with_fee'], $this->stripe->client->currency()->precision),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $data['invoices']->pluck('id'), //todo more meaningful description here:
        ];

        if ($data['token']) {
            $payment_intent_data['payment_method'] = $data['token']->token;
        } else {
            $payment_intent_data['setup_future_usage']  = 'off_session';
            // $payment_intent_data['save_payment_method'] = true;
            // $payment_intent_data['confirm'] = true;
        }

        $data['intent'] = $this->stripe->createPaymentIntent($payment_intent_data);
        $data['gateway'] = $this->stripe;

        return render('gateways.stripe.credit_card', $data);
    }

    public function paymentResponse()
    {
        # code...
    }
}
