<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Response;


class Cors
{

  public function handle($request, Closure $next)
  {

        if($request->getMethod() == "OPTIONS") {
	        header("Access-Control-Allow-Origin: *");

	        // ALLOW OPTIONS METHOD
	        $headers = [
	            'Access-Control-Allow-Methods'=> 'POST, GET, OPTIONS, PUT, DELETE',
	            'Access-Control-Allow-Headers'=> 'X-API-SECRET,X-API-TOKEN,DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range'
	        ];

            return Response::make('OK', 200, $headers);
    
        }



    return $next($request)
      ->header('Access-Control-Allow-Origin', '*')
      ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->header('Access-Control-Allow-Headers', 'X-API-SECRET,X-API-TOKEN,DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range');

  }

}