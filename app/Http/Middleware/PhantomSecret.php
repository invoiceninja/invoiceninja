<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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
