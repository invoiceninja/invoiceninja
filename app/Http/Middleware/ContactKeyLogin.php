<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use App\Http\ViewComposers\PortalComposer;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientContact;
use Auth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
            $request->session()->invalidate();
        }

        if ($request->segment(2) && $request->segment(2) == 'magic_link' && $request->segment(3)) {
            $payload = Cache::get($request->segment(3));

            if (! $payload) {
                abort(403, 'Link expired.');
            }

            $contact_email = $payload['email'];

            if ($client_contact = ClientContact::where('email', $contact_email)->where('company_id', $payload['company_id'])->first()) {
                if (empty($client_contact->email)) {
                    $client_contact->email = Str::random(15).'@example.com';
                }
                $client_contact->save();

                auth()->guard('contact')->loginUsingId($client_contact->id, true);

                if ($request->query('redirect') && ! empty($request->query('redirect'))) {
                    return redirect()->to($request->query('redirect'));
                }

                return redirect($this->setRedirectPath());
            }
        } elseif ($request->segment(3) && config('ninja.db.multi_db_enabled')) {
            if (MultiDB::findAndSetDbByContactKey($request->segment(3))) {
                if ($client_contact = ClientContact::where('contact_key', $request->segment(3))->first()) {
                    if (empty($client_contact->email)) {
                        $client_contact->email = Str::random(6).'@example.com';
                    }
                    $client_contact->save();

                    auth()->guard('contact')->loginUsingId($client_contact->id, true);

                    if ($request->query('next')) {
                        return redirect()->to($request->query('next'));
                    }

                    return redirect($this->setRedirectPath());
                }
            }
        } elseif ($request->segment(2) && $request->segment(2) == 'key_login' && $request->segment(3)) {
            if ($client_contact = ClientContact::where('contact_key', $request->segment(3))->first()) {
                if (empty($client_contact->email)) {
                    $client_contact->email = Str::random(6).'@example.com';
                    $client_contact->save();
                }

                auth()->guard('contact')->loginUsingId($client_contact->id, true);

                if ($request->query('next')) {
                    return redirect($request->query('next'));
                }

                return redirect($this->setRedirectPath());
            }
        } elseif ($request->has('client_hash') && config('ninja.db.multi_db_enabled')) {
            if (MultiDB::findAndSetDbByClientHash($request->input('client_hash'))) {
                if ($client = Client::where('client_hash', $request->input('client_hash'))->first()) {
                    $primary_contact = $client->primary_contact()->first();

                    if (empty($primary_contact->email)) {
                        $primary_contact->email = Str::random(6).'@example.com';
                    }
                    $primary_contact->save();

                    auth()->guard('contact')->loginUsingId($primary_contact->id, true);

                    return redirect($this->setRedirectPath());
                }
            }
        } elseif ($request->has('client_hash')) {
            if ($client = Client::where('client_hash', $request->input('client_hash'))->first()) {
                $primary_contact = $client->primary_contact()->first();

                if (empty($primary_contact->email)) {
                    $primary_contact->email = Str::random(6).'@example.com';
                }
                $primary_contact->save();

                auth()->guard('contact')->loginUsingId($primary_contact->id, true);

                return redirect($this->setRedirectPath());
            }
        } elseif ($request->segment(3)) {
            if ($client_contact = ClientContact::where('contact_key', $request->segment(3))->first()) {
                if (empty($client_contact->email)) {
                    $client_contact->email = Str::random(6).'@example.com';
                    $client_contact->save();
                }

                auth()->guard('contact')->loginUsingId($client_contact->id, true);

                if ($request->query('next')) {
                    return redirect($request->query('next'));
                }

                return redirect($this->setRedirectPath());
            }
        }
        //28-02-2022 middleware should not allow this to progress as we should have redirected by this stage.
        abort(404, 'Unable to authenticate.');

        return $next($request);
    }

    private function setRedirectPath()
    {
        if (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_INVOICES) {
            return '/client/invoices';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_RECURRING_INVOICES) {
            return '/client/recurring_invoices';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_QUOTES) {
            return '/client/quotes';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_CREDITS) {
            return '/client/credits';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_TASKS) {
            return '/client/tasks';
        } elseif (auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_EXPENSES) {
            return '/client/expenses';
        }
    }
}
