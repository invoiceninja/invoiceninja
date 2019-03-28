<?php

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

        $error['error'] = ['message' => 'Database could not be set'];


        if( $request->header('X-API-TOKEN') && (CompanyToken::whereRaw("BINARY `token`= ?",[$request->header('X-API-TOKEN')])->first()) && config('ninja.db.multi_db_enabled')) 
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
