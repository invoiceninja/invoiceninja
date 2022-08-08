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
use App\Models\Vendor;
use App\Models\VendorContact;
use Auth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VendorContactKeyLogin
{
    /**
     * Handle an incoming request.
     *
     * Sets a contact LOGGED IN if an appropriate vendor_hash is provided as a query parameter
     * OR
     * If the contact_key is provided in the route
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Auth::guard('vendor')->check()) {
            Auth::guard('vendor')->logout();
            $request->session()->invalidate();
        }

        if ($request->segment(2) && $request->segment(2) == 'magic_link' && $request->segment(3)) {
            $payload = Cache::get($request->segment(3));

            if (! $payload) {
                abort(403, 'Link expired.');
            }

            $contact_email = $payload['email'];

            if ($vendor_contact = VendorContact::where('email', $contact_email)->where('company_id', $payload['company_id'])->first()) {
                if (empty($vendor_contact->email)) {
                    $vendor_contact->email = Str::random(15).'@example.com';
                }
                $vendor_contact->save();

                auth()->guard('vendor')->loginUsingId($vendor_contact->id, true);

                if ($request->query('redirect') && ! empty($request->query('redirect'))) {
                    return redirect()->to($request->query('redirect'));
                }

                return redirect($this->setRedirectPath());
            }
        } elseif ($request->segment(3) && config('ninja.db.multi_db_enabled')) {
            if (MultiDB::findAndSetDbByContactKey($request->segment(3))) {
                if ($vendor_contact = VendorContact::where('contact_key', $request->segment(3))->first()) {
                    if (empty($vendor_contact->email)) {
                        $vendor_contact->email = Str::random(6).'@example.com';
                    }
                    $vendor_contact->save();

                    auth()->guard('vendor')->loginUsingId($vendor_contact->id, true);

                    if ($request->query('next')) {
                        return redirect()->to($request->query('next'));
                    }

                    return redirect($this->setRedirectPath());
                }
            }
        } elseif ($request->segment(2) && $request->segment(2) == 'key_login' && $request->segment(3)) {
            if ($vendor_contact = VendorContact::where('contact_key', $request->segment(3))->first()) {
                if (empty($vendor_contact->email)) {
                    $vendor_contact->email = Str::random(6).'@example.com';
                    $vendor_contact->save();
                }

                auth()->guard('vendor')->loginUsingId($vendor_contact->id, true);

                if ($request->query('next')) {
                    return redirect($request->query('next'));
                }

                return redirect($this->setRedirectPath());
            }
        } elseif ($request->has('vendor_hash') && config('ninja.db.multi_db_enabled')) {
            if (MultiDB::findAndSetDbByClientHash($request->input('vendor_hash'))) {
                if ($client = Vendor::where('vendor_hash', $request->input('vendor_hash'))->first()) {
                    $primary_contact = $client->primary_contact()->first();

                    if (empty($primary_contact->email)) {
                        $primary_contact->email = Str::random(6).'@example.com';
                    }
                    $primary_contact->save();

                    auth()->guard('vendor')->loginUsingId($primary_contact->id, true);

                    return redirect($this->setRedirectPath());
                }
            }
        } elseif ($request->has('vendor_hash')) {
            if ($client = Vendor::where('vendor_hash', $request->input('vendor_hash'))->first()) {
                $primary_contact = $client->primary_contact()->first();

                if (empty($primary_contact->email)) {
                    $primary_contact->email = Str::random(6).'@example.com';
                }
                $primary_contact->save();

                auth()->guard('vendor')->loginUsingId($primary_contact->id, true);

                return redirect($this->setRedirectPath());
            }
        } elseif ($request->segment(3)) {
            if ($vendor_contact = VendorContact::where('contact_key', $request->segment(3))->first()) {
                if (empty($vendor_contact->email)) {
                    $vendor_contact->email = Str::random(6).'@example.com';
                    $vendor_contact->save();
                }

                auth()->guard('vendor')->loginUsingId($vendor_contact->id, true);

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

        return 'vendor/purchase_orders';

    }
}
