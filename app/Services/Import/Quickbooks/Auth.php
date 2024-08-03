<?php
namespace App\Services\Import\Quickbooks;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Services\Import\QuickBooks\Contracts\SDKInterface as QuickbooksInterface;

final class Auth
{    
    private QuickbooksInterface $sdk;

    public function __construct(QuickbooksInterface $quickbooks) {
        $this->sdk = $quickbooks;
    }

    public function accessToken(string $code, string $realm ) : array
    {
       // TODO: Get or put token in Cache or DB?
        return $this->sdk->accessToken($code, $realm);
    }

    public function refreshToken() : array
    {
        // TODO: Get or put token in Cache or DB?
        return  $this->sdk->refreshToken();
    }

    public function getAuthorizationUrl(): string 
    {
        return $this->sdk->getAuthorizationUrl();
    }

    public function getState() : string
    {
        return $this->sdk->getState();
    }

    public function getAccessToken() : array
    {
        // TODO: Cache token and 
        $token = $this->sdk->getAccessToken();
        $access_token = $token->getAccessToken();
        $refresh_token = $token->getRefreshToken();
        $access_token_expires = $token->getAccessTokenExpiresAt();
        $refresh_token_expires = $token->getRefreshTokenExpiresAt();       
        //TODO: Cache token object. Update $sdk instance?
        return compact('access_token', 'refresh_token','access_token_expires', 'refresh_token_expires');
    }

    public function getRefreshToken() : array
    {
        // TODO: Check if token is Cached otherwise fetch a new one and Cache token and expire
        return  $this->getAccessToken();
    }
}