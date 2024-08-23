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

namespace App\Services\Import\Quickbooks;

use Carbon\Carbon;
use App\Models\Company;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;

class SdkWrapper
{
    public const MAXRESULTS = 10000;

    private $entities = ['Customer','Invoice','Payment','Item'];

    private OAuth2AccessToken $token;

    public function __construct(public DataService $sdk, private Company $company)
    {
        $this->init();
    }

    private function init(): self
    {
        
        isset($this->company->quickbooks->accessTokenKey) ? $this->setNinjaAccessToken($this->company->quickbooks) : null;

        return $this;

    }

    public function getAuthorizationUrl(): string
    {
        return $this->sdk->getOAuth2LoginHelper()->getAuthorizationCodeURL();
    }

    public function getState(): string
    {
        return $this->sdk->getOAuth2LoginHelper()->getState();
    }

    public function getRefreshToken(): string
    {
        return $this->accessToken()->getRefreshToken();
    }
    
    public function company()
    {
        nlog("getting company info");
        // nlog($this->sdk->getAccessToken());

        return $this->sdk->getCompanyInfo();
    }
    /*
    accessTokenKey
    tokenType
    refresh_token
    accessTokenExpiresAt
    refreshTokenExpiresAt
    accessTokenValidationPeriod
    refreshTokenValidationPeriod
    clientID
    clientSecret
    realmID
    baseURL
    */
    public function accessTokenFromCode(string $code, string $realm): OAuth2AccessToken
    {
        $token = $this->sdk->getOAuth2LoginHelper()->exchangeAuthorizationCodeForToken($code, $realm);

        $this->setAccessToken($token);

        return $this->accessToken();
    }
        
    /**
     * Set Stored NinjaAccessToken
     *
     * @param  mixed $token_object
     * @return self
     */
    public function setNinjaAccessToken(mixed $token_object): self
    {
        $token = new OAuth2AccessToken(
            config('services.quickbooks.client_id'),
            config('services.quickbooks.client_secret'),
            $token_object->accessTokenKey,
            $token_object->refresh_token,
            3600,
            8726400
        );

        $token->setAccessTokenExpiresAt($token_object->accessTokenExpiresAt);
        $token->setRefreshTokenExpiresAt($token_object->refreshTokenExpiresAt);
        $token->setAccessTokenValidationPeriodInSeconds(3600);
        $token->setRefreshTokenValidationPeriodInSeconds(8726400);

        $this->setAccessToken($token);

        if($token_object->accessTokenExpiresAt < time()){
            $new_token = $this->sdk->getOAuth2LoginHelper()->refreshToken();

            $this->setAccessToken($new_token);
            $this->saveOAuthToken($this->accessToken());
        }
        
        return $this;
    }
    
    /**
     * SetsAccessToken
     *
     * @param  OAuth2AccessToken $token
     * @return self
     */
    public function setAccessToken(OAuth2AccessToken $token): self
    {
        // $this->sdk = $this->sdk->updateOAuth2Token($token);

        $this->token = $token;

        return $this;
    }

    public function accessToken(): OAuth2AccessToken
    {
        return $this->token;
    }

    public function saveOAuthToken(OAuth2AccessToken $token): void
    {
        $obj = new \stdClass();
        $obj->accessTokenKey = $token->getAccessToken();
        $obj->refresh_token = $token->getRefreshToken();
        $obj->accessTokenExpiresAt = Carbon::createFromFormat('Y/m/d H:i:s', $token->getAccessTokenExpiresAt())->timestamp; //@phpstan-ignore-line - QB phpdoc wrong types!!
        $obj->refreshTokenExpiresAt = Carbon::createFromFormat('Y/m/d H:i:s', $token->getRefreshTokenExpiresAt())->timestamp; //@phpstan-ignore-line - QB phpdoc wrong types!!

        $obj->realmID = $token->getRealmID();
        $obj->baseURL = $token->getBaseURL();

        $this->company->quickbooks = $obj;
        $this->company->save();
    }


    /// Data Access ///

    public function totalRecords(string $entity): int
    {
        return (int)$this->sdk->Query("select count(*) from $entity");
    }

    private function queryData(string $query, int $start = 1, $limit = 100): array
    {
        return (array) $this->sdk->Query($query, $start, $limit);
    }

    public function fetchRecords(string $entity, int $max = 1000): array
    {

        if(!in_array($entity, $this->entities)) {
            return [];
        }

        $records = [];
        $start = 0;
        $limit = 100;
        try {
            $total = $this->totalRecords($entity);
            $total = min($max, $total);

            // Step 3 & 4: Get chunks of records until the total required records are retrieved
            do {
                $limit = min(self::MAXRESULTS, $total - $start);
                $recordsChunk = $this->queryData("select * from $entity", $start, $limit);
                if(empty($recordsChunk)) {
                    break;
                }

                $records = array_merge($records, $recordsChunk);
                $start += $limit;
            } while ($start < $total);
            if(empty($records)) {
                throw new \Exception("No records retrieved!");
            }

        } catch (\Throwable $th) {
            nlog("Fetch Quickbooks API Error: {$th->getMessage()}");
        }

        return $records;
    }
}
