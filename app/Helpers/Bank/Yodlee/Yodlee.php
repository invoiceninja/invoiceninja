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

use Illuminate\Support\Facades\Http;
 
class Yodlee
{

    public bool $test_mode;

    private string $api_endpoint = '';

    private string $test_api_endpoint = 'https://sandbox.api.yodlee.com/ysl';

    public string $fast_track_url = 'https://fl4.sandbox.yodlee.com/authenticate/restserver/fastlink';

    protected string $client_id;

    protected string $client_secret;

    protected string $admin_name;

    public function __construct(bool $test_mode = false)
    {
        $this->test_mode = $test_mode;

        if($this->test_mode)
            $this->api_endpoint = $this->test_api_endpoint;

        $this->client_id = config('ninja.yodlee.client_id');

        $this->client_secret = config('ninja.yodlee.client_secret');

        $this->admin_name = config('ninja.yodlee.admin_name');

    }

    public function getAccessToken($user = false)
    {
        if(!$user)
            $user = $this->admin_name;

        $response = $this->bankFormRequest('/auth/token', 'post', [],  ['loginName' => $user]);

        return $response->token->accessToken;
    }


    public function createUser()
    {

        $token = $this->getAccessToken();

        $user['user'] = [
            'loginName' => 'test123',
        ];

        return $this->bankRequest('/user/register', 'post', $user, ['Authorization' => $token]);

    }

    public function getAccounts($token)
    {

        $response = $this->bankRequest('/accounts', 'get', [],  ["Authorization" => "Bearer {$token}"]);

        return $response;

    }


    private function bankRequest(string $uri, string $verb, array $data = [], array $headers = [])
    {

        $response = Http::withHeaders($this->getHeaders($headers))->{$verb}($this->api_endpoint . $uri, $this->buildBody());

        if($response->successful())
            return $response->object();

        if($response->failed())
            return $response->body();

    }

    private function bankFormRequest(string $uri, string $verb, array $data = [], array $headers)
    {

        $response = Http::withHeaders($this->getFormHeaders($headers))->asForm()->{$verb}($this->api_endpoint . $uri, $this->buildBody());

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
