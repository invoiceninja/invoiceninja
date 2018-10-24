<?php

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use Closure;
use Hashids\Hashids;

class UrlSetDb
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    public function handle($request, Closure $next, $hash)
    {

        if (config('ninja.db.multi_db_enabled'))
        {
            $hashids = new Hashids(); //decoded output is _always_ an array.

            //parse URL hash and set DB
            $segments = explode("-", $hash);

            $hashed_db = $hashids->decode($segments[0]);

            MultiDB::setDB(MultiDB::DB_PREFIX . $hashed_db[0]);
        }

        return $next($request);
    }
}
