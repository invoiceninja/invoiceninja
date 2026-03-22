<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Response;

class Cors
{
    public function handle($request, Closure $next)
    {
        if ($request->getMethod() == 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');

            // ALLOW OPTIONS METHOD
            $headers = [
                'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, PUT, DELETE',
                'Access-Control-Allow-Headers' => 'X-CLIENT-PLATFORM,X-React,X-API-PASSWORD-BASE64,X-API-COMPANY-KEY,X-CLIENT-VERSION,X-API-SECRET,X-API-TOKEN,X-API-PASSWORD,DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Content-Disposition,Range,X-CSRF-TOKEN,X-XSRF-TOKEN,X-LIVEWIRE,X-Socket-ID',
            ];

            return Response::make('OK', 200, $headers);
        }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'X-React,X-API-PASSWORD-BASE64,X-API-COMPANY-KEY,X-API-SECRET,X-API-TOKEN,X-API-PASSWORD,DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Content-Disposition,Range,X-CSRF-TOKEN,X-XSRF-TOKEN,X-LIVEWIRE,X-Socket-Id');
        $response->headers->set('Access-Control-Expose-Headers', 'X-APP-VERSION,X-MINIMUM-CLIENT-VERSION,Content-Disposition');
        $response->headers->set('X-APP-VERSION', config('ninja.app_version'));
        $response->headers->set('X-MINIMUM-CLIENT-VERSION', config('ninja.minimum_client_version'));

        return $response;
    }
}
