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

use App\Models\ClientContact;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckClientExistence
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $multiple_contacts = ClientContact::query()
            ->where('email', auth('contact')->user()->email)
            ->whereNotNull('email')
            ->distinct('company_id')
            ->whereHas('client', function ($query) {
                return $query->whereNull('deleted_at');
            })
            ->get();

        if (count($multiple_contacts) == 0) {
            Auth::logout();

            return redirect()->route('client.login');
        }

        if (count($multiple_contacts) == 1) {
            Auth::guard('contact')->login($multiple_contacts[0], true);
        }

        session()->put('multiple_contacts', $multiple_contacts);

        return $next($request);
    }
}
