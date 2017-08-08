<?php

use Codeception\Util\Fixtures;
use Faker\Factory;
use Codeception\Util\Debug;

class APICest
{
    private $faker;
    private $token;

    public function _before(AcceptanceTester $I)
    {
        $this->faker = Factory::create();

        Debug::debug('Create/get token');
        $data = new stdClass;
        $data->email = Fixtures::get('username');
        $data->password = Fixtures::get('password');
        $data->api_secret = Fixtures::get('api_secret');
        $data->token_name = 'iOS Token';

        $response = $this->sendRequest('login', $data);
        $userAccounts = $response->data;

        PHPUnit_Framework_Assert::assertGreaterThan(0, count($userAccounts));

        $userAccount = $userAccounts[0];
        $this->token = $userAccount->token;

        Debug::debug("Token: {$this->token}");
    }

    public function testAPI(AcceptanceTester $I)
    {
        $I->wantTo('test the API');

        $data = new stdClass;
        $data->contact = new stdClass;
        $data->contact->email = $this->faker->safeEmail;
        $clientId = $this->createEntity('client', $data);
        $this->listEntities('clients');

        $data = new stdClass;
        $data->client_id = $clientId;
        $data->description = $this->faker->realText(100);
        $this->createEntity('task', $data);
        $this->listEntities('tasks');

        $lineItem = new stdClass;
        $lineItem->qty = $this->faker->numberBetween(1, 10);
        $lineItem->cost = $this->faker->numberBetween(1, 10);
        $data = new stdClass;
        $data->client_id = $clientId;
        $data->invoice_items = [
            $lineItem
        ];
        $invoiceId = $this->createEntity('invoice', $data);
        $this->listEntities('invoices');

        $data = new stdClass;
        $data->invoice_id = $invoiceId;
        $data->amount = 1;
        $this->createEntity('payment', $data);
        $this->listEntities('payments');

        $data = new stdClass;
        $data->name = $this->faker->word;
        $data->rate = $this->faker->numberBetween(1, 10);
        $this->createEntity('tax_rate', $data);
        $this->listEntities('tax_rates');

        $data = new stdClass;
        $data->product_key = $this->faker->word;
        $data->notes = $this->faker->realText(100);
        $this->createEntity('product', $data);
        $this->listEntities('products');

        $data = new stdClass;
        $data->name = $this->faker->word;
        $data->vendor_contacts = [];
        $this->createEntity('vendor', $data);
        $this->listEntities('vendors');

        $data = new stdClass;
        $data->client_id = $clientId;
        $data->amount = 1;
        $this->createEntity('credit', $data);
        $this->listEntities('credits');

        $this->listEntities('accounts');
        $this->listEntities('dashboard');

    }

    private function createEntity($entityType, $data)
    {
        Debug::debug("Create {$entityType}");

        $response = $this->sendRequest("{$entityType}s", $data);
        $entityId = $response->data->id;

        PHPUnit_Framework_Assert::assertGreaterThan(0, $entityId);

        return $entityId;
    }

    private function listEntities($entityType)
    {
        Debug::debug("List {$entityType}");
        $response = $this->sendRequest("{$entityType}", null, 'GET');

        PHPUnit_Framework_Assert::assertGreaterThan(0, count($response->data));

        return $response;
    }

    private function sendRequest($url, $data, $type = 'POST')
    {
        $url = Fixtures::get('url') . '/api/v1/' . $url;
        $data = json_encode($data);
        $curl = curl_init();

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $type,
            CURLOPT_POST => $type === 'POST' ? 1 : 0,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data),
                'X-Ninja-Token: '. $this->token,
            ],
        ];

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        curl_close($curl);

        //Debug::debug('Response: ' . $response);

        return json_decode($response);
    }
}
