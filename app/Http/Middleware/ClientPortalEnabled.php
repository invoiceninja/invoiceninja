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
        /** @var \App\Models\ClientContact $client_contact */
        $client_contact = auth()->user();

        if ($client_contact->client->getSetting('enable_client_portal') === false) {
            return redirect()->route('client.error')->with(['title' => ctrans('texts.client_portal'), 'notification' => 'This section of the app has been disabled by the administrator.']);
        }

        return $next($request);
    }
}
