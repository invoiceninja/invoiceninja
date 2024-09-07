<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\CBAPowerBoard;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\CBAPowerBoardPaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;

class CreditCard implements LivewireMethodInterface
{
    public function __construct(public CBAPowerBoardPaymentDriver $powerboard)
    {
    }

    public function authorizeView(array $data)
    {
        return render('gateways.powerboard.credit_card.authorize', $this->paymentData($data));
    }

    public function authorizeResponse($request)
    {
        $cgt = $this->storePaymentMethod($request);

        return redirect()->route('client.payment_methods.index');

    }

    private function getCustomer(): array
    {
        return [
            'first_name' => $this->powerboard->client->present()->first_name(),
            'last_name' => $this->powerboard->client->present()->first_name(),
            'email' => $this->powerboard->client->present()->email(),
            'phone' => $this->powerboard->client->present()->phone(),
            'type' => 'card',
            'address_line1' => $this->powerboard->client->address1 ?? '',
            'address_line2' => $this->powerboard->client->address2 ?? '',
            'address_state' => $this->powerboard->client->state ?? '',
            'address_country' => $this->powerboard->client->country->iso_3166_3 ?? '',
            'address_city' => $this->powerboard->client->city ?? '',
            'address_postcode' => $this->powerboard->client->postal_code ?? '',
        ];
    }
    private function storePaymentMethod($request)
    {

        $this->powerboard->init();

        $payment_source = $request->gateway_response;
        
        $payload = array_merge($this->getCustomer(), [
            'token' => $payment_source,
            'store_ccv' => true,
        ]);

        $r = $this->powerboard->gatewayRequest('/v1/vault/payment_sources', (\App\Enum\HttpVerb::POST)->value, $payload, []);

        // {
        //     "status": 201,
        //     "error": null,
        //     "resource": {
        //         "type": "payment_source",
        //         "data": {
        //             "type": "card",
        //             "_source_ip_address": "54.86.50.139",
        //             "expire_month": 1,
        //             "expire_year": 2023,
        //             "card_name": "John  Citizen",
        //             "card_number_last4": "4242",
        //             "card_number_bin": "42424242",
        //             "card_scheme": "visa",
        //             "ref_token": "cus_hyyau7dpojJttR",
        //             "status": "active",
        //             "created_at": "2021-08-05T07:04:25.974Z",
        //             "company_id": "5d305bfbfac31b4448c738d7",
        //             "vault_token": "c90dbe45-7a23-4f26-9192-336a01e58e59",
        //             "updated_at": "2021-08-05T07:05:56.035Z"
        //         }
        //     }
        // }


        if($r->failed())
            return $this->powerboard->processInternallyFailedPayment($this->powerboard, $r->throw());

        nlog("payment source saving");

        $response_payload = $r->object();

        nlog($response_payload);

        try {

            $payment_meta = new \stdClass();
            $payment_meta->exp_month = (string) $response_payload->resource->data->expire_month;
            $payment_meta->exp_year = (string) $response_payload->resource->data->expire_year;
            $payment_meta->brand = (string) $response_payload->resource->data->card_scheme;
            $payment_meta->last4 = (string) $response_payload->resource->data->card_number_last4;
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $response_payload->resource->data->vault_token,
                'payment_method_id' => $request->payment_method_id,
            ];

            //['gateway_customer_reference' => $response_payload->resource->data->ref_token]
            $cgt = $this->powerboard->storeGatewayToken($data, []);

            $customer_payload = [
                'payment_source' => [
                    'vault_token' => $cgt->token,
                    'address_line1' => $this->powerboard->client->address1 ?? '',
                    'address_line2' => $this->powerboard->client->address1 ?? '',
                    'address_state' => $this->powerboard->client->state ?? '',
                    'address_country' => $this->powerboard->client->country->iso_3166_3 ?? '',
                    'address_city' => $this->powerboard->client->city ?? '',
                    'address_postcode' => $this->powerboard->client->postcode ?? '',    
                ],
            ];

            foreach ($customer_payload['payment_source'] as $key => $value) {

                if (strlen($value ??  '') == 0) {
                    unset($customer_payload['payment_source'][$key]);
                }

            }

            $customer = $this->powerboard->customer()->findOrCreateCustomer($customer_payload);

            $cgt->gateway_customer_reference = $customer->_id;
            $cgt->save();

            //test that payment token is attached to customer here
            
            $hit=false;
            foreach($customer->payment_sources as $source){
                if($source->vault_token == $cgt->token)
                    $hit = true;
            }

            if(!$hit)
                $this->powerboard->customer()->addTokenToCustomer($cgt->token, $customer);

            return $cgt;

        } catch (\Exception $e) {
            return $this->powerboard->processInternallyFailedPayment($this->powerboard, $e);
        }

    }


    public function paymentData(array $data): array
    {
        
        $merge = [
            'public_key' => $this->powerboard->company_gateway->getConfigField('publicKey'),
            'widget_endpoint' => $this->powerboard->widget_endpoint,
            'gateway' => $this->powerboard,
            'environment' => $this->powerboard->environment,
        ];

        return array_merge($data, $merge);
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.powerboard.credit_card.pay', $data);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.powerboard.credit_card.pay_livewire';
    }

    public function tokenBilling($request, $cgt, $client_present = false)
    {

    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        nlog($request->all());

        $token = $request->payment_source;
        $payload = [];

        if($request->store_card) {

            nlog("Store Payment Method");

            $customer = $this->storePaymentMethod($request);

            nlog($customer);

            $payload["customer"] = [
                    "payment_source" => [
                        "vault_token" => "c90dbe45-7a23-4f26-9192-336a01e58e59",
                        "gateway_id" => "5dde1f3799cfea21ed2fc942"
                    ]
                ];
        }

        $uri = '/v1/charges';

        $payload = [
            "amount" => "10.00",
                "currency" =>"AUD",
                
        ];

        $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::POST)->value, $payload, []);

        // $payload = [
        //     'amount' => $this->powerboard->payment_hash->amount_with_fee(),
        //     'currency' => $this->powerboard->client->currency()->code,
        //     'description' => $this->powerboard->getDescription(),
        //     // 'descriptor' => ,
        //     // 'reference' => ,
        //     // 'reference2' => ,
        //     // 'amount_surcharge' => ,
        //     // 'amount_original' => ,
        //     // 'initialization_source' => ,
        //     'bypass_3ds' => false,
        //     // 'token'=> ,
        //     'payment_source_id' => $request->payment_source,
        //     // 'customer_id' => ,
        //     'customer' => $this->getCustomer(),
        // ];



        // $this->stripe->init();

        // $state = [
        //     'server_response' => json_decode($request->gateway_response),
        //     'payment_hash' => $request->payment_hash,
        // ];

        // $state = array_merge($state, $request->all());
        // $state['store_card'] = boolval($state['store_card']);

        // if ($request->has('token') && ! is_null($request->token)) {
        //     $state['store_card'] = false;
        // }

        // $state['payment_intent'] = PaymentIntent::retrieve($state['server_response']->id, array_merge($this->stripe->stripe_connect_auth, ['idempotency_key' => uniqid("st", true)]));
        // $state['customer'] = $state['payment_intent']->customer;

        // $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
        // $this->stripe->payment_hash->save();

        // $server_response = $this->stripe->payment_hash->data->server_response;

        // if ($server_response->status == 'succeeded') {
        //     $this->stripe->logSuccessfulGatewayResponse(['response' => json_decode($request->gateway_response), 'data' => $this->stripe->payment_hash], SystemLog::TYPE_STRIPE);

        //     return $this->processSuccessfulPayment();
        // }

        // return $this->processUnsuccessfulPayment($server_response);
    }

    public function processSuccessfulPayment()
    {
        // UpdateCustomer::dispatch($this->stripe->company_gateway->company->company_key, $this->stripe->company_gateway->id, $this->stripe->client->id);

        // $stripe_method = $this->stripe->getStripePaymentMethod($this->stripe->payment_hash->data->server_response->payment_method);

        // $data = [
        //     'payment_method' => $this->stripe->payment_hash->data->server_response->payment_method,
        //     'payment_type' => PaymentType::parseCardType(strtolower($stripe_method->card->brand)) ?: PaymentType::CREDIT_CARD_OTHER,
        //     'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->server_response->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
        //     'transaction_reference' => isset($this->stripe->payment_hash->data->payment_intent->latest_charge) ? $this->stripe->payment_hash->data->payment_intent->latest_charge : optional($this->stripe->payment_hash->data->payment_intent->charges->data[0])->id,
        //     'gateway_type_id' => GatewayType::CREDIT_CARD,
        // ];

        // $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['amount' => $data['amount']]);
        // $this->stripe->payment_hash->save();

        // if ($this->stripe->payment_hash->data->store_card) {
        //     $customer = new \stdClass();
        //     $customer->id = $this->stripe->payment_hash->data->customer;

        //     $this->stripe->attach($this->stripe->payment_hash->data->server_response->payment_method, $customer);

        //     $stripe_method = $this->stripe->getStripePaymentMethod($this->stripe->payment_hash->data->server_response->payment_method);

        //     $this->storePaymentMethod($stripe_method, $this->stripe->payment_hash->data->payment_method_id, $customer);
        // }

        // $payment = $this->stripe->createPayment($data, Payment::STATUS_COMPLETED);

        // SystemLogger::dispatch(
        //     ['response' => $this->stripe->payment_hash->data->server_response, 'data' => $data],
        //     SystemLog::CATEGORY_GATEWAY_RESPONSE,
        //     SystemLog::EVENT_GATEWAY_SUCCESS,
        //     SystemLog::TYPE_STRIPE,
        //     $this->stripe->client,
        //     $this->stripe->client->company,
        // );

        // if ($payment->invoices()->whereHas('subscription')->exists()) {
        //     $subscription = $payment->invoices()->first()->subscription;

        //     if ($subscription && array_key_exists('return_url', $subscription->webhook_configuration) && strlen($subscription->webhook_configuration['return_url']) >= 1) {
        //         return redirect($subscription->webhook_configuration['return_url']);
        //     }
        // }

        // return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
    }

    public function processUnsuccessfulPayment($server_response)
    {
        // $this->stripe->sendFailureMail($server_response->cancellation_reason);

        // $message = [
        //     'server_response' => $server_response,
        //     'data' => $this->stripe->payment_hash->data,
        // ];

        // SystemLogger::dispatch(
        //     $message,
        //     SystemLog::CATEGORY_GATEWAY_RESPONSE,
        //     SystemLog::EVENT_GATEWAY_FAILURE,
        //     SystemLog::TYPE_STRIPE,
        //     $this->stripe->client,
        //     $this->stripe->client->company,
        // );

        // throw new PaymentFailed('Failed to process the payment.', 500);
    }

}
