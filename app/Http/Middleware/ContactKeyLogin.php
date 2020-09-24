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
use App\Models\ClientContact;
use App\Models\CompanyToken;
use Closure;
use Auth;

class ContactKeyLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        if ($request->segment(3) && config('ninja.db.multi_db_enabled')) {

            if (MultiDB::findAndSetDbByContactKey($request->segment(3))) {
                
                $client_contact = ClientContact::where('contact_key', $request->segment(3))->first();
                Auth::guard('contact')->login($client_contact, true);
                return redirect()->to('client/dashboard');

            }

        } 
        else if ($request->has('contact_key')) {

            if($client_contact = ClientContact::where('contact_key', $request->segment(3))->first()){
                Auth::guard('contact')->login($client_contact, true);
                return redirect()->to('client/dashboard');
            }

        }

        return $next($request);
    }
}
