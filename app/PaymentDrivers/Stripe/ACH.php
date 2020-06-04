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
use Stripe\Exception\InvalidRequestException;

class ACH
{
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView(array $data)
    {
        return render('gateways.stripe.ach.authorize', array_merge($data));
    }

    public function authorizeResponse($request)
    {
        $state = [
            'server_response' => json_decode($request->gateway_response),
            'gateway_id' => $request->gateway_id,
            'gateway_type_id' => $request->gateway_type_id,
            'is_default' => $request->is_default,
        ];

        $customer = $this->stripe->findOrCreateCustomer();

        $this->stripe->init();

        $local_stripe = new \Stripe\StripeClient(
            $this->stripe->company_gateway->getConfigField('apiKey')
        );

        try {
            $local_stripe->customers->createSource(
                $customer->id,
                ['source' => $state['server_response']->token->id]
            );
        } catch (InvalidRequestException $e) {
            return back()->with('ach_error', $e->getMessage());
        }

        $payment_meta = $state['server_response']->token->bank_account;
        $payment_meta->brand = ctrans('texts.ach');
        $payment_meta->type = ctrans('texts.bank_transfer');
        $payment_meta->verified_at = null;
        $payment_meta->btok = $state['server_response']->token->id;

        $client_gateway_token = new ClientGatewayToken();
        $client_gateway_token->company_id = $this->stripe->client->company->id;
        $client_gateway_token->client_id = $this->stripe->client->id;
        $client_gateway_token->token = $state['server_response']->token->bank_account->id;
        $client_gateway_token->company_gateway_id = $this->stripe->company_gateway->id;
        $client_gateway_token->gateway_type_id = $state['gateway_type_id'];
        $client_gateway_token->gateway_customer_reference = $customer->id;
        $client_gateway_token->meta = $payment_meta;
        $client_gateway_token->save();

        if ($state['is_default'] == 'true' || $this->stripe->client->gateway_tokens->count() == 1) {
            $this->stripe->client->gateway_tokens()->update(['is_default' => 0]);

            $client_gateway_token->is_default = 1;
            $client_gateway_token->save();
        }

        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data)
    {
        $state = [
            'amount' => $this->stripe->convertToStripeAmount($data['amount_with_fee'], $this->stripe->client->currency()->precision),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'invoices' => $data['invoices'],
            'gateway' => $this->stripe,
            'payment_method_id' => GatewayType::BANK_TRANSFER, // needs verification
            'token' => $data['token'],
            'customer' => $this->stripe->findOrCreateCustomer(),
        ];

        return render('gateways.stripe.ach.pay', $state);
    }

    public function paymentResponse($request)
    {
        $state = [
            'payment_method' => $request->payment_method_id,
            'gateway_type_id' => $request->company_gateway_id,
            'hashed_ids' => $request->hashed_ids,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'source' => $request->source,
            'customer' => $request->customer,
        ];

        $this->stripe->init();

        try {
            $charge = \Stripe\Charge::create([
                'amount' => $state['amount'],
                'currency' => $state['currency'],
                'customer' => $state['customer'],
                'source' => $state['source'],
            ]);
        } catch (\Exception $e) {
            if ($e instanceof \Stripe\Exception\CardException) {
                return redirect()
                    ->route('client.payment_methods.verification', ClientGatewayToken::first()->hashed_id)
                    ->with('message', $e->getMessage());
            }
        }
    }

    public function verificationView(ClientGatewayToken $token)
    {
        return render('gateways.stripe.ach.verify', compact('token'));
    }

    public function processVerification(ClientGatewayToken $token)
    {
        $this->stripe->init();

        $bank_account = \Stripe\Customer::retrieveSource(
            request()->customer,
            request()->source,
        );

        try {
            $status = $bank_account->verify(['amounts' => request()->transactions]);
        } catch (\Stripe\Exception\CardException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function acceptWebhook()
    {
        // ..
    }
}
