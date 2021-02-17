<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Company;
use App\Utils\Ninja;
use Closure;
use Illuminate\Http\Request;

class ContactRegister
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Resolving based on subdomain. Used in version 5 hosted platform.
        if ($request->subdomain) {
            $company = Company::where('subdomain', $request->subdomain)->firstOrFail();

            abort_unless($company->getSetting('enable_client_registration'), 404);

            $request->merge(['key' => $company->company_key]);

            return $next($request);
        }

        // For self-hosted platforms with multiple companies, resolving is done using company key
        // if it doesn't resolve using a domain.
        if ($request->route()->parameter('company_key') && Ninja::isSelfHost()) {
            $company = Company::where('company_key', $request->company_key)->firstOrFail();

            abort_unless($company->client_can_register, 404);

            return $next($request);
        }

        // As a fallback for self-hosted, it will use default company in the system
        // if key isn't provided in the url.
        if (!$request->route()->parameter('company_key') && Ninja::isSelfHost()) {
            $company = Account::first()->default_company;

            abort_unless($company->client_can_register, 404);

            $request->merge(['key' => $company->company_key]);

            return $next($request);
        }

        return abort(404);
    }
}
