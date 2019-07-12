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
use Closure;

class SetDomainNameDb
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

        $error['error'] = ['message' => 'Database could not be set'];

        /* 
         * Use the host name to set the active DB
         **/
        if( $request->getHttpHost() && config('ninja.db.multi_db_enabled') && ! MultiDB::findAndSetDbByDomain($request->getHttpHost())) 
        {

            return response()->json(json_encode($error, JSON_PRETTY_PRINT) ,403);
        
        }

        return $next($request);
    }


}
