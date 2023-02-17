<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\Yodlee;

use App\Exceptions\YodleeApiException;
use App\Helpers\Bank\Yodlee\Transformer\AccountTransformer;
use App\Helpers\Bank\Yodlee\Transformer\IncomeTransformer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Yodlee
{
    public bool $test_mode = false;

    private string $api_endpoint = 'https://production.api.yodlee.com/ysl';

    private string $dev_api_endpoint = 'https://sandbox.api.yodlee.com/ysl';
    
    private string $test_api_endpoint = 'https://development.api.yodlee.com/ysl';

    public string $dev_fast_track_url = 'https://fl4.sandbox.yodlee.com/authenticate/restserver/fastlink';

    public string $test_fast_track_url = 'https://fl4.preprod.yodlee.com/authenticate/USDevexPreProd3-449/fastlink?channelAppName=usdevexpreprod3';

    public string $production_track_url = 'https://fl4.prod.yodlee.com/authenticate/USDevexProd3-331/fastlink?channelAppName=usdevexprod3';

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

        $this->test_mode = config('ninja.yodlee.test_mode');

        config('ninja.yodlee.dev_mode') ? $this->setDevUrl() : null;
    }

    public function getFastTrackUrl()
    {
        if (config('ninja.yodlee.dev_mode')) {
            return $this->dev_fast_track_url;
        }
        
        return $this->test_mode ? $this->test_fast_track_url : $this->production_track_url;
    }

    public function setTestMode()
    {
        $this->test_mode = true;

        return $this;
    }

    public function setDevUrl()
    {
        $this->test_api_endpoint = $this->dev_api_endpoint;

        $this->api_endpoint = $this->dev_api_endpoint;

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
        if ($is_admin) {
            $user = $this->admin_name;
        } else {
            $user = $this->bank_account_id ?: $this->admin_name;
        }

        $response = $this->bankFormRequest('/auth/token', 'post', [], ['loginName' => $user]);

        return $response->token->accessToken;
    }


    public function createUser($company)
    {
        $token = $this->getAccessToken(true);

        $user['user'] = [
            'loginName' => Str::uuid(),
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

        if ($response->successful()) {
            return $response->object();
        }

        if ($response->failed()) {
            throw new YodleeApiException($response->body());
        }
    }

    public function getAccounts($params = [])
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/accounts", $params);

        if ($response->successful()) {
            $at = new AccountTransformer();
            return $at->transform($response->object());
        }

        if ($response->failed()) {
            throw new YodleeApiException($response->body());
        }
    }

    public function getAccount($account_id)
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/accounts/{$account_id}", []);

        if ($response->successful()) {
            return true;
        }

        if ($response->failed()) {
            return false;
        }
    }

    public function deleteAccount($account_id)
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->delete($this->getEndpoint(). "/accounts/{$account_id}", []);

        if ($response->successful()) {
            return true;
        }

        if ($response->failed()) {
            throw new YodleeApiException($response->body());
        }
    }


    public function getTransactions($params = [])
    {
        $token = $this->getAccessToken();
 
        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/transactions", $params);

        if ($response->successful()) {
            // return $response->object();
            $it = new IncomeTransformer();
            return $it->transform($response->object());
        }

        if ($response->failed()) {
            throw new YodleeApiException($response->body());
        }
    }

    public function getTransactionCount($params = [])
    {
        $token = $this->getAccessToken();
 
        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/transactions/count", $params);

        if ($response->successful()) {
            return $response->object();
        }

        if ($response->failed()) {
            throw new YodleeApiException($response->body());
        }
    }

    public function getTransactionCategories($params = [])
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/transactions/categories", $params);

        if ($response->successful()) {
            return $response->object();
        }

        if ($response->failed()) {
            throw new YodleeApiException($response->body());
        }
    }

    private function bankFormRequest(string $uri, string $verb, array $data, array $headers)
    {
        $response = Http::withHeaders($this->getFormHeaders($headers))->asForm()->{$verb}($this->getEndpoint() . $uri, $this->buildBody());

        if ($response->successful()) {
            return $response->object();
        }

        if ($response->failed()) {
            throw new YodleeApiException($response->body());
        }
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
