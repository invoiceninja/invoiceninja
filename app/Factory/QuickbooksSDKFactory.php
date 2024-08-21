<?php

namespace App\Factory;

use App\Libraries\MultiDB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\DataService\DataService;
use App\Services\Import\Quickbooks\Repositories\CompanyTokensRepository;


class QuickbooksSDKFactory
{
    public static function create()
    {
        $tokens = [];
        // Ensure the user is authenticated
        if(($user = Auth::user()))
        {
            $company = $user->company();

            $token_store = (new CompanyTokensRepository($company->company_key));
            $tokens  = array_filter($token_store->get());
            if(!empty($tokens)) {
                $keys =  ['refreshTokenKey','QBORealmID'];
                if(array_key_exists('access_token', $tokens)) {
                    $keys = array_merge(['accessTokenKey'] ,$keys);
                }
                
                $tokens = array_combine($keys, array_values($tokens));
            }
        }

        $config = $tokens + config('services.quickbooks.settings') + [
            'state' => Str::random(12)
        ];
        $sdk = DataService::Configure($config);
        if (env('APP_DEBUG')) { 
            $sdk->setLogLocation(storage_path("logs/quickbooks.log"));
            $sdk->enableLog();
        }

        $sdk->setMinorVersion("73");
        $sdk->throwExceptionOnError(true);
        if(array_key_exists('refreshTokenKey', $config) && !array_key_exists('accessTokenKey', $config)) 
        {
            $tokens = ($sdk->getOAuth2LoginHelper())->refreshToken();
            $sdk = $sdk->updateOAuth2Token($tokens);
            $tokens = ($sdk->getOAuth2LoginHelper())->getAccessToken();
            $access_token = $tokens->getAccessToken();
            $realm = $tokens->getRealmID();
            $refresh_token = $tokens->getRefreshToken();
            $access_token_expires = $tokens->getAccessTokenExpiresAt();
            $refresh_token_expires = $tokens->getRefreshTokenExpiresAt();     
            $tokens = compact('access_token', 'refresh_token','access_token_expires', 'refresh_token_expires','realm');
            $token_store->save($tokens);
        }

        return $sdk;
    }
}
