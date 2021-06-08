<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
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
        }

        if ($request->segment(2) && $request->segment(2) == 'magic_link' && $request->segment(3)) {
            $payload = Cache::get($request->segment(3));
            $contact_email = $payload['email'];
            
            if($client_contact = ClientContact::where('email', $contact_email)->where('company_id', $payload['company_id'])->first()){
               
                 if(empty($client_contact->email))
                    $client_contact->email = Str::random(15) . "@example.com"; $client_contact->save();
    
                auth()->guard('contact')->login($client_contact, true);

                if ($request->query('redirect') && !empty($request->query('redirect'))) {
                    return redirect()->to($request->query('redirect'));
                }

                return redirect()->to('client/dashboard');
            }
        }
        elseif ($request->segment(3) && config('ninja.db.multi_db_enabled')) {
            if (MultiDB::findAndSetDbByContactKey($request->segment(3))) {

            if($client_contact = ClientContact::where('contact_key', $request->segment(3))->first()){
                if(empty($client_contact->email))
                    $client_contact->email = Str::random(6) . "@example.com"; $client_contact->save();

                Auth::guard('contact')->login($client_contact, true);
                return redirect()->to('client/dashboard');
             }

            }
        } elseif ($request->segment(2) && $request->segment(2) == 'key_login' && $request->segment(3)) {
            if ($client_contact = ClientContact::where('contact_key', $request->segment(3))->first()) {
  
                  if(empty($client_contact->email))
                    $client_contact->email = Str::random(6) . "@example.com"; $client_contact->save();
    
                auth()->guard('contact')->login($client_contact, true);
                return redirect()->to('client/dashboard');
            }
        } elseif ($request->has('client_hash') && config('ninja.db.multi_db_enabled')) {
            if (MultiDB::findAndSetDbByClientHash($request->input('client_hash'))) {

                if($client = Client::where('client_hash', $request->input('client_hash'))->first()){
        
                $primary_contact = $client->primary_contact()->first();

                if(empty($primary_contact->email))
                    $primary_contact->email = Str::random(6) . "@example.com"; $primary_contact->save();

                    auth()->guard('contact')->login($primary_contact, true);
                    return redirect()->to('client/dashboard');
                }
            }
        } elseif ($request->has('client_hash')) {
            if ($client = Client::where('client_hash', $request->input('client_hash'))->first()) {

                $primary_contact = $client->primary_contact()->first();
                
                if(empty($primary_contact->email))
                    $primary_contact->email = Str::random(6) . "@example.com"; $primary_contact->save();

                    auth()->guard('contact')->login($primary_contact, true);

                return redirect()->to('client/dashboard');
            }
        }


        return $next($request);
    }
}
