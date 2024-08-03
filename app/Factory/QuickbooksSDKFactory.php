<?php

namespace App\Factory;

use App\Libraries\MultiDB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use QuickBooksOnline\API\DataService\DataService;

class QuickbooksSDKFactory
{
    public static function create()
    {
        $tokens = [];
        // Ensure the user is authenticated
        if(($user = Auth::user()))
        {
            $company = $user->company();
            MultiDB::findAndSetDbByCompanyKey($company->company_key);
            // Retrieve token from the database
            if(($quickbooks = DB::table('companies')->where('id', $company->id)->select(['quickbook_refresh_token','quickbooks_realm_id'])->first())) {
                $refreshTokenKey = $quickbooks->quickbooks_refresh_token;
                $QBORealmID = $quickbooks->quickbooks_realm_id;
                // Retrieve value from cache
                $accessTokenKey = Cache::get($company->company_key);
                $tokens = compact('accessTokenKey','refreskTokenKey','QBORealmID');
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

        return $sdk;
    }
}
