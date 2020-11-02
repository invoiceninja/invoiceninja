<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;

class ContactRegister
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
        /*
         * Notes:
         *
         * 1. If request supports subdomain (for hosted) check domain and continue request.
         * 2. If request doesn't support subdomain and doesn' have company_key, abort
         * 3. firstOrFail() will abort with 404 if company with company_key wasn't found.
         * 4. Abort if setting isn't enabled.
         */

        if ($request->subdomain) {
            $company = Company::where('subdomain', $request->subdomain)->firstOrFail();

            abort_unless($company->getSetting('enable_client_registration'), 404);

            return $next($request);
        }

        abort_unless($request->company_key, 404);

        $company = Company::where('company_key', $request->company_key)->firstOrFail();

        abort_unless($company->client_can_register, 404);

        return $next($request);
    }
}
