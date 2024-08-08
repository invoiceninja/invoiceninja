<?php

namespace App\Factory;

use App\Libraries\MultiDB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\DataService\DataService;
use App\Services\Import\Quickbooks\Auth as QuickbooksService;
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

            $tokens = (new CompanyTokensRepository($company->company_key));
            $tokens  = array_filter($tokens->get());
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
            $auth = new QuickbooksService($sdk);
            $tokens = $auth->refreshTokens();
            $auth->saveTokens($tokens);
        }

        return $sdk;
    }
}
