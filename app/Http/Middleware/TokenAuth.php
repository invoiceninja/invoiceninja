<?php

namespace App\Http\Middleware;

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

        if($request->header('X-API-TOKEN') 
            && ($user = CompanyToken::whereRaw("BINARY `token`= ?",[$request->header('X-API-TOKEN')])->user)) {

            auth()->login($user);
        
        }
        else {

            $error['error'] = ['message' => 'Invalid token'];

            return response()->json(json_encode($error, JSON_PRETTY_PRINT) ,403);
        }

        return $next($request);
    }
}
