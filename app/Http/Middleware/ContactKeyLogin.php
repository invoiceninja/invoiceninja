<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientContact;
use Auth;
use Closure;
use Illuminate\Http\Request;

class ContactKeyLogin
{
    /**
     * Handle an incoming request.
     *
     * Sets a contact LOGGED IN if an appropriate client_hash is provided as a query parameter
     * OR
     * If the contact_key is provided in the route
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Auth::guard('contact')->check()) {
            Auth::guard('contact')->logout();
        }

        if ($request->segment(3) && config('ninja.db.multi_db_enabled')) {
            if (MultiDB::findAndSetDbByContactKey($request->segment(3))) {
                $client_contact = ClientContact::where('contact_key', $request->segment(3))->first();
                Auth::guard('contact')->login($client_contact, true);
                return redirect()->to('client/dashboard');
            }
        } elseif ($request->has('contact_key')) {
            if ($client_contact = ClientContact::where('contact_key', $request->segment(3))->first()) {
                Auth::guard('contact')->login($client_contact, true);
                return redirect()->to('client/dashboard');
            }
        } elseif ($request->has('client_hash') && config('ninja.db.multi_db_enabled')) {
            if (MultiDB::findAndSetDbByClientHash($request->input('client_hash'))) {
                $client = Client::where('client_hash', $request->input('client_hash'))->first();
                Auth::guard('contact')->login($client->primary_contact()->first(), true);
                return redirect()->to('client/dashboard');
            }
        } elseif ($request->has('client_hash')) {
            if ($client = Client::where('client_hash', $request->input('client_hash'))->first()) {
                Auth::guard('contact')->login($client->primary_contact()->first(), true);
                return redirect()->to('client/dashboard');
            }
        }

        return $next($request);
    }
}
