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

class SetDb
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
            'message' => 'Invalid Token',
            'errors' => []
        ];

        // we must have a token passed, that matched a token in the db, and multiDB is enabled.
        // todo i don't think we can call the DB prior to setting it???? i think this if statement needs to be rethought
        //if( $request->header('X-API-TOKEN') && (CompanyToken::whereRaw("BINARY `token`= ?",[$request->header('X-API-TOKEN')])->first()) && config('ninja.db.multi_db_enabled')) 
        if( $request->header('X-API-TOKEN') && config('ninja.db.multi_db_enabled')) 
        {

            if(! MultiDB::findAndSetDb($request->header('X-API-TOKEN')))
            {

            return response()->json(json_encode($error, JSON_PRETTY_PRINT) ,403);

            }
        
        }
        else {


            return response()->json(json_encode($error, JSON_PRETTY_PRINT) ,403);
            
        }

        return $next($request);
    }


}
