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

use App\Libraries\MultiDB;
use App\Models\CompanyToken;
use Closure;

class PasswordProtection
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

        $error = [
            'message' => 'Invalid Password',
            'errors' => []
        ];

        if( $request->header('X-API-TOKEN') && $request->header('X-API-PASSWORD') && config('ninja.db.multi_db_enabled')) 
        {

            if(! MultiDB::findAndSetDb($request->header('X-API-TOKEN')))
            {

                return response()->json($error, 403);

            }
        
        }
        else {


                return response()->json($error, 403);
            
        }

        return $next($request);
    }


}
