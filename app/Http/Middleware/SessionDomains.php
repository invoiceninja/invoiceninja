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

use App\Utils\Ninja;
use Closure;
use Illuminate\Http\Request;

class SessionDomains
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Ninja::isSelfHost()) {
            return $next($request);
        }

        $domain_name = $request->getHost();

        if (strpos($domain_name, config('ninja.app_domain')) !== false) {
        } else {
            config(['session.domain' => $domain_name]);
        }

        return $next($request);
    }
}
