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

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Exceptions\PaymentFailed;
use App\PaymentDrivers\CBAPowerBoardPaymentDriver;
use App\PaymentDrivers\CBAPowerBoard\Models\Charge;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\CBAPowerBoard\Models\PaymentSource;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

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
        $data = [
            'first_name' => $this->powerboard->client->present()->first_name(),
            'last_name' => $this->powerboard->client->present()->first_name(),
            'email' => $this->powerboard->client->present()->email(),
            // 'phone' => $this->powerboard->client->present()->phone(),
            // 'type' => 'card',
            'address_line1' => $this->powerboard->client->address1 ?? '',
            'address_line2' => $this->powerboard->client->address2 ?? '',
            'address_state' => $this->powerboard->client->state ?? '',
            'address_country' => $this->powerboard->client->country->iso_3166_3 ?? '',
            'address_city' => $this->powerboard->client->city ?? '',
            'address_postcode' => $this->powerboard->client->postal_code ?? '',
        ];

        return \App\Helpers\Sanitizer::removeBlanks($data);

    }

    private function storePaymentSource($request)
    {

        $this->powerboard->init();

        $payment_source = $request->gateway_response;
        
        $payload = array_merge($this->getCustomer(), [
            'token' => $payment_source,
            "vault_type" => "session",
            'store_ccv' => true,
        ]);

        $r = $this->powerboard->gatewayRequest('/v1/vault/payment_sources', (\App\Enum\HttpVerb::POST)->value, $payload, []);

        if($r->failed())
            return $this->powerboard->processInternallyFailedPayment($this->powerboard, $r->throw());

        nlog($r->object());

        $source = (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(PaymentSource ::class, $r->object()->resource->data);

        return $source;

        // $cgt = $this->powerboard->customer()->storePaymentMethod(payment_source: $source, store_card: $request->store_card);

        // return $cgt;

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

    private function get3dsToken(PaymentSource $source, $request)
    {

        $payment_hash = PaymentHash::query()->where('hash', $request->payment_hash)->first();

        $browser_details = json_decode($request->browser_details,true);

        $payload = [
            "amount" => $payment_hash->data->amount_with_fee,
            "currency" => $this->powerboard->client->currency()->code,
            "description" => $this->powerboard->getDescription(),
            "customer" => [
                "payment_source" => [
                    "vault_token" => $source->vault_token,
                    "gateway_id" => '66d65c5a68b7fa297a31c267',
                ],
            ],
            "_3ds" => [
                "browser_details" => $browser_details,
            ],
        ];

        nlog($payload);

        $r = $this->powerboard->gatewayRequest('/v1/charges/3ds', (\App\Enum\HttpVerb::POST)->value, $payload, []);

        nlog($r->body());

        if($r->failed())
            $r->throw();

        $charge = $r->json();
        nlog($charge['resource']['data']);
        return response()->json($charge['resource']['data'], 200);

    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        nlog($request->all());
        $payment_hash = PaymentHash::where('hash', $request->payment_hash)->first();

        // $token = $request->payment_source;
        $payload = [];

        /** Token Payment */
        if($request->input('token', false))
        {
            $cgt = $this->powerboard
                        ->client
                        ->gateway_tokens()
                        ->where('company_gateway_id', $this->powerboard->company_gateway->id)
                        ->where('token', $request->token)
                        ->first();

            $payload["customer"] = [
                    "payment_source" => [
                        "vault_token" => $cgt->token,
                        "gateway_id" => $cgt->meta->gateway_id
                    ]
                ];

        }
        elseif($request->browser_details)
        {
            $payment_source = $this->storePaymentSource($request);   

            return $this->get3dsToken($payment_source, $request);
            
        }
        elseif($request->charge) {

            $charge_request = json_decode($request->charge, true);

            nlog($charge_request);

            $payload = [
                '_3ds' => [
                    'id' => $charge_request['charge_3ds_id'],
                ],
                "amount"=> $payment_hash->data->amount_with_fee,
                "currency"=> $this->powerboard->client->currency()->code,
                "store_cvv"=> true,
            ];

            nlog($payload);

            $r = $this->powerboard->gatewayRequest("/v1/charges", (\App\Enum\HttpVerb::POST)->value, $payload, []);

            if($r->failed())
                $r->throw();

            $charge = (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(Charge::class, $r->object()->resource->data) ?? $r->throw();

            if ($charge->status == 'complete') {
                $this->powerboard->logSuccessfulGatewayResponse(['response' => $charge, 'data' => $this->powerboard->payment_hash], SystemLog::TYPE_POWERBOARD);
                return $this->processSuccessfulPayment($charge);
            }


        }



        nlog($request->all());

        // else {
        
        //     $payload["customer"] = [
        //         "payment_source" => [
        //             "vault_token" => $cgt->token,
        //             "gateway_id" => $cgt->meta->gateway_id
        //         ]
        //     ];
    
        // }

        // $uri = '/v1/charges';

        // $payload_meta = [
        //     "amount" => $payment_hash->data->amount_with_fee,
        //     "currency" => $this->powerboard->client->currency()->code,
        //     "description" => $this->powerboard->getDescription(), 
        // ];

        // $payload = array_merge($payload, $payload_meta);
        
        // nlog($payload);

        // $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::POST)->value, $payload, []);

        // if($r->failed())
        //     $r->throw();

        // nlog($r->object());

        // return $this->processUnsuccessfulPayment($r->body());
    }

    public function processSuccessfulPayment(Charge $charge)
    {

        $data = [
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'amount' => $this->powerboard->payment_hash->data->amount_with_fee,
            'transaction_reference' => $charge->_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->powerboard->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $charge, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_POWERBOARD,
            $this->powerboard->client,
            $this->powerboard->client->company,
        );

        if ($payment->invoices()->whereHas('subscription')->exists()) {
            $subscription = $payment->invoices()->first()->subscription;

            if ($subscription && array_key_exists('return_url', $subscription->webhook_configuration) && strlen($subscription->webhook_configuration['return_url']) >= 1) {
                return redirect($subscription->webhook_configuration['return_url']);
            }
        }

        return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
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
