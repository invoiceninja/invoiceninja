<?php

namespace App\Services\Import\Quickbooks;

use App\Services\Import\Quickbooks\Contracts\SdkInterface as QuickbooksInterface;

final class SdkWrapper implements QuickbooksInterface
{
    
    const MAXRESULTS = 10000;

    private $sdk;
    private $entities = ['Customer','Invoice','Payment','Item'];

    public function __construct($sdk)
    {
        // Prep Data Services
        $this->sdk = $sdk;
    }

    public function getAuthorizationUrl() : string 
    {
        return ($this->sdk->getOAuth2LoginHelper())->getAuthorizationCodeURL();
    }

    public function getState() : string
    {
        return ($this->sdk->getOAuth2LoginHelper())->getState();
    }

    public function getAccessToken() : array
    {
        return $this->getTokens();
    }

    public function getRefreshToken(): array{
        return $this->getTokens();
    }

    public function accessToken(string $code, string $realm) : array 
    {
        $token = ($this->sdk->getOAuth2LoginHelper())->exchangeAuthorizationCodeForToken($code,$realm);
       
        return $this->getTokens();
    }

    private function getTokens() : array {
        
        $token =($this->sdk->getOAuth2LoginHelper())->getAccessToken();
        $access_token = $token->getAccessToken();
        $refresh_token = $token->getRefreshToken();
        $access_token_expires = $token->getAccessTokenExpiresAt();
        $refresh_token_expires = $token->getRefreshTokenExpiresAt();

        return compact('access_token', 'refresh_token','access_token_expires', 'refresh_token_expires');
    }

    public function refreshToken(): array
    {
        $token = ($this->sdk->getOAuth2LoginHelper())->refreshToken();
        $this->sdk = $this->sdk->updateOAuth2Token($token);

        return $this->getTokens();
    }

    public function handleCallbacks(array $data): void {

    }

    public function totalRecords(string $entity) : int {
        return $this->sdk->Query("select count(*) from $entity");
    }

    private function queryData(string $query, int $start = 1, $limit = 100) : array 
    {
        return (array) $this->sdk->Query($query, $start, $limit);
    }

    public function fetchRecords( string $entity, int $max = 1000): array {
        
        if(!in_array($entity, $this->entities)) return [];
        
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
                if(empty($recordsChunk)) break;

                $records = array_merge($records,$recordsChunk);
                $start += $limit;
            } while ($start < $total);
            if(empty($records)) throw new \Exceptions("No records retrieved!");

        } catch (\Throwable $th) {
            nlog("Fetch Quickbooks API Error: {$th->getMessage()}");
        }

        return $records;
    }
}
