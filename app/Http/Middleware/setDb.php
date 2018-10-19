<?php

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
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
        if (! config('auth.providers.users.driver') == 'eloquent')
        {
            MultiDB::setDB(auth()->user()->db);
        }

        return $next($request);
    }
}
