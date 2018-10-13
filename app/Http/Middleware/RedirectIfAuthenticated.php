<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param string|null              $guard
     *
     * @return mixed
     */
    //
    public function handle($request, Closure $next, $guard = null)
    {
        Log::error('the guard is '. $guard);

        switch ($guard) {
            case 'user' :
                if (Auth::guard($guard)->check()) {
                    return redirect('dashboard');
                }
                break;
            case 'client':
                if(Auth::guard($guard)->check()){
                    return redirect()->route('client.home');
                }
                break;
            default:
                if (Auth::guard($guard)->check()) {
                    return redirect('default');
                }
                break;
        }
        return $next($request);
    }
//
}
