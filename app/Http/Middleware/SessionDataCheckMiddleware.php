<?php namespace App\Http\Middleware;

use Closure;
use Auth;
use Session;

// https://arjunphp.com/laravel5-inactivity-idle-session-logout/
class SessionDataCheckMiddleware {

    /**
     * Check session data, if role is not valid logout the request
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $bag = Session::getMetadataBag();
        $max = env('IDLE_TIMEOUT_MINUTES', 6 * 60) * 60; // minute to second conversion
        $elapsed = time() - $bag->getLastUsed();

        if ( ! $bag || $elapsed > $max) {
            $request->session()->flush();
            Auth::logout();
            $request->session()->flash('warning', trans('texts.inactive_logout'));
        }

        return $next($request);
    }
}
