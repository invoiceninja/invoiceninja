<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use App\Libraries\OAuth\Providers\Google;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;

class PasswordProtection
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
    
        $error = [
            'message' => 'Invalid Password',
            'errors' => new stdClass,
        ];

        if (Cache::get(auth()->user()->hashed_id.'_logged_in')) {

            Cache::pull(auth()->user()->hashed_id.'_logged_in');
            Cache::add(auth()->user()->hashed_id.'_logged_in', Str::random(64), now()->addMinutes(30));

            return $next($request);

        }elseif( $request->header('X-API-OAUTH-PASSWORD') && strlen($request->header('X-API-OAUTH-PASSWORD')) >=1){

            //user is attempting to reauth with OAuth - check the token value
            //todo expand this to include all OAuth providers
            $user = false;
            $google = new Google();
            $user = $google->getTokenResponse(request()->header('X-API-OAUTH-PASSWORD'));

            if (is_array($user)) {
                
                $query = [
                    'oauth_user_id' => $google->harvestSubField($user),
                    'oauth_provider_id'=> 'google'
                ];

                //If OAuth and user also has a password set  - check both
                if ($existing_user = MultiDB::hasUser($query)  && auth()->user()->has_password && Hash::check(auth()->user()->password, $request->header('X-API-PASSWORD'))) {

                    Cache::add(auth()->user()->hashed_id.'_logged_in', Str::random(64), now()->addMinutes(30));
                    return $next($request);
                }
                elseif($existing_user = MultiDB::hasUser($query) && !auth()->user()->has_password){

                    Cache::add(auth()->user()->hashed_id.'_logged_in', Str::random(64), now()->addMinutes(30));
                    return $next($request);                    
                }
            }

            return response()->json($error, 412);


        }elseif ($request->header('X-API-PASSWORD') && Hash::check($request->header('X-API-PASSWORD'), auth()->user()->password))  {

            Cache::add(auth()->user()->hashed_id.'_logged_in', Str::random(64), now()->addMinutes(30));

            return $next($request);

        } else {

            return response()->json($error, 412);
        }


    }
}