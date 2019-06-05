<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use App\Events\User\UserLoggedIn;
use App\Models\CompanyToken;
use App\Models\User;
use Closure;

class TokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        if( $request->header('X-API-TOKEN') && ($user = CompanyToken::whereRaw("BINARY `token`= ?",[$request->header('X-API-TOKEN')])->first()->user ) ) 
        {
            
            auth()->login($user);
            event(new UserLoggedIn($user));
        }
        else {

            return response()->json(json_encode(['message' => 'Invalid token'], JSON_PRETTY_PRINT) ,403);
        }

        return $next($request);
    }
}
