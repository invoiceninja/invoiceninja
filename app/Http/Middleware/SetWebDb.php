<?php

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use Closure;
use Cookie;

class SetWebDb
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
        if (config('ninja.db.multi_db_enabled')) {
            MultiDB::setDB(Cookie::get('db'));
        }

        return $next($request);
    }
}
