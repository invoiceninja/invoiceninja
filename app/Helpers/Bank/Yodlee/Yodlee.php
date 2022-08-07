<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\Yodlee;

use App\Helpers\Bank\Yodlee\Transformer\AccountTransformer;
use Illuminate\Support\Facades\Http;
 
class Yodlee
{

    public bool $test_mode = false;

    private string $api_endpoint = 'https://production.api.yodlee.com/ysl';

    private string $test_api_endpoint = 'https://sandbox.api.yodlee.com/ysl';

    public string $fast_track_url = 'https://fl4.sandbox.yodlee.com/authenticate/restserver/fastlink';

    protected string $client_id;

    protected string $client_secret;

    protected string $admin_name;

    protected ?string $bank_account_id;

    public function __construct(?string $bank_account_id = null)
    {

        $this->bank_account_id = $bank_account_id;

        $this->client_id = config('ninja.yodlee.client_id');

        $this->client_secret = config('ninja.yodlee.client_secret');

        $this->admin_name = config('ninja.yodlee.admin_name');

    }

    public function setTestMode()
    {
        $this->test_mode = true;

        return $this;
    }

    public function getEndpoint()
    {

        return $this->test_mode ? $this->test_api_endpoint : $this->api_endpoint;

    }

    /**
     * If we do not pass in a user
     * we pass in the admin username instead
     */
    public function getAccessToken($is_admin = false)
    {
        if($is_admin)
            $user = $this->admin_name;
        else
            $user = $this->bank_account_id ?: $this->admin_name;

        $response = $this->bankFormRequest('/auth/token', 'post', [],  ['loginName' => $user]);
//catch failures here
        nlog($response);
        return $response->token->accessToken;
    }


    public function createUser()
    {

        $token = $this->getAccessToken(true);

        $user['user'] = [
            'loginName' => 'test123',
        ];

/*
{
  "user": {
    "preferences": {
      "dateFormat": "string",
      "timeZone": "string",
      "currency": "USD",
      "locale": "en_US"
    },
    "address": {
      "zip": "string",
      "country": "string",
      "address3": "string",
      "address2": "string",
      "city": "string",
      "address1": "string",
      "state": "string"
    },
    "loginName": "string",
    "name": {
      "middle": "string",
      "last": "string",
      "fullName": "string",
      "first": "string"
    },
    "email": "string",
    "segmentName": "string"
  }
}
*/

        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->post($this->getEndpoint(). "/user/register", $user);

        if($response->successful())
            return $response->object();

        if($response->failed())
            return $response->body();


        return $response;

    }

    public function getAccounts($params = [])
    {

        $token = $this->getAccessToken();

        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/accounts", $params);


        if($response->successful()){

            $at = new AccountTransformer();
            return $at->transform($response->object());
            // return $response->object();
        }

        if($response->failed())
            return $response->body();


        return $response;

    }

    public function getTransactions($params = [])
    {
        $token = $this->getAccessToken();
 
        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/transactions", $params);

        if($response->successful())
            return $response->object();

        if($response->failed())
            return $response->body();

    }

    public function getTransactionCategories($params = [])
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/transactions/categories", $params);

        if($response->successful())
            return $response->object();

        if($response->failed())
            return $response->body();

    }

    private function bankFormRequest(string $uri, string $verb, array $data = [], array $headers)
    {

        $response = Http::withHeaders($this->getFormHeaders($headers))->asForm()->{$verb}($this->getEndpoint() . $uri, $this->buildBody());

        if($response->successful())
            return $response->object();

        if($response->failed())
            return $response->body();

    }

    private function getHeaders($data = [])
    {
        return array_merge($data, [
            'Api-Version' => '1.1',
            'ContentType' => 'application/json'
        ]);
    }


    private function getFormHeaders($data = [])
    {
        return array_merge($data, [
            'Api-Version' => '1.1',
        ]);
    }

    private function buildBody()
    {

        return [
            'clientId' => $this->client_id,
            'secret' => $this->client_secret,
        ];

    }

}
