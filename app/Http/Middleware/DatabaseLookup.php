<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;

class DatabaseLookup
{
    public function handle(Request $request, Closure $next)
    {
        if (env('MULTI_DB_ENABLED') && ! session('SESSION_DB_SERVER')) {
            return redirect('/logout');
        }

        return $next($request);
    }
}
