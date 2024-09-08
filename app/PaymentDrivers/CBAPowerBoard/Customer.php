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

use App\Helpers\Sanitizer;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\CBAPowerBoard\Models\Customer as ModelsCustomer;
use App\PaymentDrivers\CBAPowerBoard\Models\PaymentSource;
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

        nlog($r->json());

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

        nlog($search_results);

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
            // 'phone' => $this->powerboard->client->present()->phone(),
        ];

        
        $payload = array_merge($payload, $data);
        
        $payload = Sanitizer::removeBlanks($payload);

        nlog($payload);

        $uri = "/v1/customers";

        $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::POST)->value, $payload, []);

        if($r->failed())
            $r->throw();

        // $this->storePaymentMethod($r->object());

        return (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(ModelsCustomer::class, $r->object()->resource->data) ?? $r->throw();

    }

    public function storePaymentMethod(?PaymentSource $payment_source = null, ?ModelsCustomer $customer = null, bool $store_card = false): ClientGatewayToken
    {

        // $response_payload = $customer->resource->data;
        // $source = end($customer->resource->data->payment_sources);
        /** @var PaymentSource $source */
        $source = $payment_source ? $payment_source : end($customer->payment_sources);

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = (string) $source->expire_month;
        $payment_meta->exp_year = (string) $source->expire_year;
        $payment_meta->brand = (string) $source->card_scheme;
        $payment_meta->last4 = (string) $source->card_number_last4;
        $payment_meta->gateway_id = is_null($source->gateway_id) ? (string) $source->gateway_id : false;
        $payment_meta->type = \App\Models\GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $source->vault_token,
            'payment_method_id' => \App\Models\GatewayType::CREDIT_CARD,
        ];

        $additional_data = $customer ? ['gateway_customer_reference' => $customer->_id] : [];
        $cgt = $this->powerboard->storeGatewayToken($data, $additional_data);

        if($customer || !$store_card)
            return $cgt;
            
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

        $customer = $this->findOrCreateCustomer($customer_payload);

        $this->addTokenToCustomer($cgt->token, $customer);

        return $cgt->fresh();

    }


    public function addTokenToCustomer(string $token, ModelsCustomer $customer): mixed
    {
        nlog("add token to customer");

        $uri = "/v1/customers/{$customer->_id}";
    
        $payload = [
            'payment_source' => [
                'vault_token' => $token,
            ]
        ];

        $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::POST)->value, $payload, []);

        if($r->failed()){
            nlog($r->body());
            return $r->throw(); 
        }

        nlog($r->object());

        $customer = (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(ModelsCustomer::class, $r->object()->resource->data);
        
        $source = collect($customer->payment_sources)->first(function (PaymentSource $source) use ($token){
            return $token == $source->vault_token;
        });

        nlog("i found the source");
        nlog($source);

        $cgt = $this->powerboard
                    ->client
                    ->gateway_tokens()
                    ->where('token', $token)
                    ->first();

        nlog($cgt->id);

        $meta = $cgt->meta;
        $meta->gateway_id = $source->gateway_id;
        $cgt->meta = $meta;
        $cgt->save();

        return $r->object();
    }

}

