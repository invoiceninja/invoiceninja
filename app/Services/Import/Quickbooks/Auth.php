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

final class Auth
{
    public function __construct(private SdkWrapper $sdk)
    {
    }

    public function accessToken(string $code, string $realm): array
    {
        // TODO: Get or put token in Cache or DB?
        return $this->sdk->accessToken($code, $realm);
    }

    public function refreshToken(): array
    {
        // TODO: Get or put token in Cache or DB?
        return  $this->sdk->refreshToken();
    }

    public function getAuthorizationUrl(): string
    {
        return $this->sdk->getAuthorizationUrl();
    }

    public function getState(): string
    {
        return $this->sdk->getState();
    }

    public function getAccessToken(): array
    {
        $tokens = [];
        // $token_store = new CompanyTokensRepository();
        // $tokens = $token_store->get();
        // if(empty($tokens)) {
        //     $token = $this->sdk->getAccessToken();
        //     $access_token = $token->getAccessToken();
        //     $realm = $token->getRealmID();
        //     $refresh_token = $token->getRefreshToken();
        //     $access_token_expires = $token->getAccessTokenExpiresAt();
        //     $refresh_token_expires = $token->getRefreshTokenExpiresAt();
        //     $tokens = compact('access_token', 'refresh_token','access_token_expires', 'refresh_token_expires','realm');
        // }

        return $tokens;
    }

    public function getRefreshToken(): array
    {
        return  $this->getAccessToken();
    }
}
