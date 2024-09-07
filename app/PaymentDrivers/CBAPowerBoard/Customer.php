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

use App\Models\ClientGatewayToken;
use App\PaymentDrivers\CBAPowerBoard\Models\Customer as ModelsCustomer;
use App\PaymentDrivers\CBAPowerBoardPaymentDriver;

class Customer
{
    public function __construct(public CBAPowerBoardPaymentDriver $powerboard)
    {
    }

    public function findOrCreateCustomer(array $customer_data): mixed 
    {
        $token = $this->powerboard
                        ->client
                        ->gateway_tokens()
                        ->whereNotNull('gateway_customer_reference')
                        ->where('company_gateway_id', $this->powerboard->company_gateway->id)
                        ->first();

        if($token && $customer = $this->getCustomer($token->gateway_customer_reference)){
            return (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(ModelsCustomer::class, $customer->resource->data);
        }

        if($customer = $this->findCustomer())
            return (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(ModelsCustomer::class, $customer);


        return $this->createCustomer($customer_data);

    }

    public function getCustomer(string $id): mixed
    {
        $uri = "/v1/customers/{$id}";

        $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::GET)->value, [], []);

        if($r->successful())
            return $r->object();


        return false;
    }

    public function findCustomer(): mixed
    {
        $uri = '/v1/customers';

        $query = [
            'reference' => $this->powerboard->client->client_hash,
        ];

        $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::GET)->value, $query, []);

        $search_results = $r->object();

        $customers = $search_results->resource->data;

        return reset($customers); // returns first element or false

    }

    public function createCustomer(array $data = []): object
    {
       
        $payload = [
            'company_name' => $this->powerboard->client->present()->name(),
            'first_name' => $this->powerboard->client->present()->first_name(),
            'last_name' => $this->powerboard->client->present()->first_name(),
            'email' => $this->powerboard->client->present()->email(),
            'reference' => $this->powerboard->client->client_hash,
            'phone' => $this->powerboard->client->present()->phone(),
        ];

        foreach($payload as $key => $value){

            if(strlen($value ??  '') == 0)
                unset($payload[$key]);

        }

        $payload = array_merge($payload, $data);

        nlog($payload);

        $uri = "/v1/customers";

        $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::POST)->value, $payload, []);

        if($r->successful())
            $this->storePaymentMethod($r->object());

        return (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(ModelsCustomer::class, $r->object()->resource->data) ?? $r->throw();

    }

    private function storePaymentMethod(mixed $customer): ClientGatewayToken
    {

        $response_payload = $customer->resource->data;
        $source = end($customer->resource->data->payment_sources);

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = (string) $source->expire_month;
        $payment_meta->exp_year = (string) $source->expire_year;
        $payment_meta->brand = (string) $source->card_scheme;
        $payment_meta->last4 = (string) $source->card_number_last4;
        $payment_meta->gateway_id = (string) $source->gateway_id;
        $payment_meta->type = \App\Models\GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $source->vault_token,
            'payment_method_id' => \App\Models\GatewayType::CREDIT_CARD,
        ];

        $cgt = $this->powerboard->storeGatewayToken($data, ['gateway_customer_reference' => $response_payload->_id]);

        return $cgt;

    }


    public function addTokenToCustomer(string $token, mixed $customer): mixed
    {
    
        $uri = "/v1/customers/{$customer->_id}";
    
        $payload = [
            'payment_source' => [
                'vault_token' => $token,
            ]
        ];

        $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::POST)->value, $payload, []);

        return $r->successful() ? $r->object() : $r->throw();
    }

}

