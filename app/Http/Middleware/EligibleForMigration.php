<?php

namespace App\Http\Middleware;

use Closure;

class EligibleForMigration
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (auth()->user()->eligibleForMigration()) {
            return $next($request);
        }

        return redirect('/settings/account_management');
    }
}
