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

    public function getAccountSummary($account_id)
    {
        $token = $this->getAccessToken();

        $response = Http::withHeaders($this->getHeaders(["Authorization" => "Bearer {$token}"]))->get($this->getEndpoint(). "/accounts/{$account_id}", []);

        if ($response->successful()) {
            return $response->object();
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

    /**
     * updateEligibility
     *
     * ALLOW_UPDATE
     * ALLOW_UPDATE_WITH_CREDENTIALS
     * DISALLOW_UPDATE
     */

    /**
     * additionalStatus
     *
     * LOGIN_IN_PROGRESS
     * DATA_RETRIEVAL_IN_PROGRESS
     * ACCT_SUMMARY_RECEIVED
     * AVAILABLE_DATA_RETRIEVED
     * PARTIAL_DATA_RETRIEVED
     * DATA_RETRIEVAL_FAILED
     * DATA_NOT_AVAILABLE
     * ACCOUNT_LOCKED
     * ADDL_AUTHENTICATION_REQUIRED
     * BETA_SITE_DEV_IN_PROGRESS
     * CREDENTIALS_UPDATE_NEEDED
     * INCORRECT_CREDENTIALS
     * PROPERTY_VALUE_NOT_AVAILABLE
     * INVALID_ADDL_INFO_PROVIDED
     * REQUEST_TIME_OUT
     * SITE_BLOCKING_ERROR
     * UNEXPECTED_SITE_ERROR
     * SITE_NOT_SUPPORTED
     * SITE_UNAVAILABLE
     * TECH_ERROR
     * USER_ACTION_NEEDED_AT_SITE
     * SITE_SESSION_INVALIDATED
     * NEW_AUTHENTICATION_REQUIRED
     * DATASET_NOT_SUPPORTED
     * ENROLLMENT_REQUIRED_FOR_DATASET
     * CONSENT_REQUIRED
     * CONSENT_EXPIRED
     * CONSENT_REVOKED
     * INCORRECT_OAUTH_TOKEN
     * MIGRATION_IN_PROGRESS
     */

    /**
     * IN_PROGRESS	LOGIN_IN_PROGRESS	 	Provider login is in progress.
     * IN_PROGRESS	USER_INPUT_REQUIRED	 	Provider site requires MFA-based authentication and needs user input for login.
     * IN_PROGRESS	LOGIN_SUCCESS	 	Provider login is successful.
     * IN_PROGRESS	ACCOUNT_SUMMARY_RETRIEVED	 	Account summary info may not have the complete info of accounts that are available in the provider site. This depends on the sites behaviour. Account summary info may not be available at all times.
     * FAILED	NEVER_INITIATED	 	The add or update provider account was not triggered due to techincal reasons. This is a rare occurrence and usually resolves quickly.
     * FAILED	LOGIN_FAILED	 	Provider login failed.
     * FAILED	REQUEST_TIME_OUT	 	The process timed out.
     * FAILED	 	DATA_RETRIEVAL_FAILED	All accounts under the provider account failed with same or different errors, though login was successful.
     * FAILED	 	 	No additional status or information will be provided when there are errors other than the ones listed above.
     * PARTIAL_SUCCESS	PARTIAL_DATA_RETRIEVED	DATA_RETRIEVAL_FAILED_PARTIALLY	One/few accounts data gathered and one/few accounts failed.
     * PARTIAL_SUCCESS	PARTIAL_DATA_RETRIEVED_REM_SCHED	DATA_RETRIEVAL_FAILED_PARTIALLY	One/few accounts data gathered One/few accounts failed
     * SUCCESS	 	 	All accounts under the provider was added or updated successfully.
     */

    /**
    * updateEligibility
    *
    * ALLOW_UPDATE	                       The status indicates that the account is eligible for the next update and applies to both MFA and non-MFA accounts. For MFA-based accounts, the user may have to provide the MFA details during account refresh.
    * ALLOW_UPDATE_WITH_CREDENTIALS	The status indicates updating or refreshing the account by directing the user to edit the provided credentials.
    * DISALLOW_UPDATE	                The status indicates the account is not eligible for the update or refresh process due to a site issue or a technical error.
    */

}
