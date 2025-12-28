<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PhantomSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (config('ninja.phantomjs_secret') && $request->has('phantomjs_secret') && (config('ninja.phantomjs_secret') == $request->input('phantomjs_secret'))) {
            return $next($request);
        }

        return redirect('/');
    }
}
