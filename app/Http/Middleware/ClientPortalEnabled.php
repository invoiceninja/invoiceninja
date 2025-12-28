<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ClientPortalEnabled
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

        auth()->guard('contact')->user()->loadMissing(['client' => function ($query) {
            $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }]);

        if (auth()->guard('contact')->user()->client->getSetting('enable_client_portal') === false) {
            return redirect()->route('client.error')->with(['title' => ctrans('texts.client_portal'), 'notification' => 'This section of the app has been disabled by the administrator.']);
        }

        return $next($request);
    }
}
