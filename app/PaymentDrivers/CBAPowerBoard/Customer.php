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
use App\PaymentDrivers\CBAPowerBoardPaymentDriver;

class Customer
{
    public function __construct(public CBAPowerBoardPaymentDriver $powerboard)
    {
    }

    public function findOrCreateCustomer(string $payment_source): mixed 
    {
        $token = $this->powerboard
                        ->client
                        ->gateway_tokens()
                        ->where('company_gateway_id', $this->powerboard->company_gateway->id)
                        ->first();

        if($token && $customer = $this->getCustomer($token->gateway_customer_reference)){
            return $customer;
        }

        if($customer = $this->findCustomer())
            return $customer;

        return $this->createCustomer(['token' => $payment_source]);

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

        return reset($search_results->resource->data); // returns first element or false

    }

    /*
    token	+2	string(UIID)	One-time token with all the payment source information
    reference	-	string	Manually defined reference for customer in payment systems
    description	-	string	Customer description. This is customer internal description
    company_name	-	string	Customer company name
    first_name	-	string	Customer first name
    last_name	-	string	Customer last name
    email	-	string	Customer email
    phone	-	string(E.164)	Customer phone in E.164 international notation (Example: +12345678901)
    default_source	+	string (24 hex characters)	Payment source used by default
    payment_source	+	object	Object with payment information
    payment_source.gateway_id	-4	string (24 hex characters)	Gateway id
    payment_source.vault_token	+3	string (UIID)	Vault token
    payment_source.type	+	string	Type of payment. card for payment with credit card
    payment_source.card_name	+1	string	Cardholder name (as on card)
    payment_source.card_number	+1	string(numeric)	Card number
    payment_source.expire_month	+1	string(mm)	Card expiration month mm
    payment_source.expire_year	+1	string(yyyy)	Card expiration year
    payment_source.card_ccv	-1	string(numeric)	Card CCV number
    payment_source.address_line1	-	string	Customer Address, line 1
    payment_source.address_line2	-	string	Customer Address, line 2
    payment_source.address_state	-	string	Customer Address, State
    payment_source.address_country	-	string	Customer Address, Country Code
    payment_source.address_city	-	string	Customer Address, City
    payment_source.address_postcode
    */
    public function createCustomer(array $data = []): object
    {

        // 'address_line1' => $this->powerboard->client->address1 ?? '',
        // 'address_line2' => $this->powerboard->client->address2 ?? '',
        // 'address_state' => $this->powerboard->client->state ?? '',
        // 'address_country' => $this->powerboard->client->country->iso_3166_3 ?? '',
        // 'address_city' => $this->powerboard->client->city ?? '',
        // 'address_postcode' => $this->powerboard->client->postal_code ?? '',

        $payload = [
            'first_name' => $this->powerboard->client->present()->first_name(),
            'last_name' => $this->powerboard->client->present()->first_name(),
            'email' => $this->powerboard->client->present()->email(),
            'phone' => $this->powerboard->client->present()->phone(),
            'reference' => $this->powerboard->client->client_hash,
        ];

        $payload = array_merge($payload, $data);

        $uri = "/v1/customers";

        $r = $this->powerboard->gatewayRequest($uri, (\App\Enum\HttpVerb::POST)->value, $payload, []);

        if($r->successful())
            $this->storePaymentMethod($r->object());

        return $r->object() ?? $r->throw();

    }

    /*
    {
        "status": 201,
        "error": null,
        "resource": {
            "type": "customer",
            "data": {
                "statistics": {
                    "successful_transactions": 0,
                    "total_collected_amount": 0
                },
                "_check_expire_date": false,
                "archived": false,
                "_source_ip_address": "130.41.62.108",
                "_id": "64b09a3d1e04e51be27f16c5",
                "company_id": "63cf32a154a870183bf2398a",
                "email": "john@test.com",
                "first_name": "John",
                "last_name": "Customer",
                "phone": "+61111111111",
                "reference": "Customer 1",
                "default_source": "64b09a341e04e51be27f16c2",
                "_service": {
                    "default_gateway_id": "63cf37b142194166721498e9"
                },
                "payment_sources": [
                    {
                        "type": "card",
                        "_id": "64b09a341e04e51be27f16c2",
                        "expire_month": 1,
                        "expire_year": 2039,
                        "card_name": "John Customer",
                        "card_scheme": "mastercard",
                        "card_number_last4": "1118",
                        "card_number_bin": "51111111",
                        "ref_token": "9191664642213170",
                        "status": "active",
                        "created_at": "2023-07-14T00:43:32.375Z",
                        "gateway_id": "63cf37b142194166721498e9",
                        "gateway_type": "MasterCard",
                        "gateway_name": "CommWeb",
                        "vault_token": "b944dfb0-35f4-47d6-a306-2b79cebf34f3",
                        "updated_at": "2023-07-14T00:43:41.169Z"
                    }
                ],
                "payment_destinations": [],
                "updated_at": "2023-07-14T00:43:41.170Z",
                "created_at": "2023-07-14T00:43:41.170Z",
                "__v": 0
            }
        }
    }
    */
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



    // public function updateCustomer(string $id, $data): object
    // {

    // }
}

