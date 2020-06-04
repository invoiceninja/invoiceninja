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
        } catch(InvalidRequestException $e) {
            return back()->with('ach_error', $e->getMessage());
        }

        $payment_meta = $state['server_response']->token->bank_account;
        $payment_meta->brand = ctrans('texts.ach');
        $payment_meta->type = ctrans('texts.bank_transfer');
        $payment_meta->verified_at = null;

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

    public function paymentResponse($request)
    {
        $server_response = json_decode($request->input('gateway_response'));

        $state = [
            'payment_method' => $server_response->payment_method,
            'payment_status' => $server_response->status,
            'save_card' => $request->store_card,
            'gateway_type_id' => $request->payment_method_id,
            'hashed_ids' => $request->hashed_ids,
            'server_response' => $server_response,
        ];
    }

    public function acceptWebhook()
    {
        // ..
    }
}
