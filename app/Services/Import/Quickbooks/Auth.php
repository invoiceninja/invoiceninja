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
}