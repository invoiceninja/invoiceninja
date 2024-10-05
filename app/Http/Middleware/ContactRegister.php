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
        $domain_name = $request->getHost();

        /* Hosted */
        if (strpos($domain_name, config('ninja.app_domain')) !== false) {
            $subdomain = explode('.', $domain_name)[0];

            $query = [
                'subdomain' => $subdomain,
                'portal_mode' => 'subdomain',
            ];

            $company = Company::query()->where($query)->first();

            if ($company) {
                if (! $company->client_can_register) {
                    abort(400, 'Registration disabled');
                }

                session()->put('company_key', $company->company_key);

                return $next($request);
            }
        }

        /* Hosted */
        if (Ninja::isHosted()) {
            $query = [
                'portal_domain' => $request->getSchemeAndHttpHost(),
                'portal_mode' => 'domain',
            ];

            if ($company = Company::query()->where($query)->first()) {
                if (! $company->client_can_register) {
                    abort(400, 'Registration disabled');
                }

                // $request->merge(['key' => $company->company_key]);
                session()->put('company_key', $company->company_key);

                return $next($request);
            }
        }

        // For self-hosted platforms with multiple companies, resolving is done using company key
        // if it doesn't resolve using a domain.

        if ($request->company_key && Ninja::isSelfHost() && $company = Company::query()->where('company_key', $request->company_key)->first()) {
            if (! (bool) $company->client_can_register) {
                abort(400, 'Registration disabled');
            }

            //$request->merge(['key' => $company->company_key]);
            session()->put('company_key', $company->company_key);

            return $next($request);
        }

        // As a fallback for self-hosted, it will use default company in the system
        // if key isn't provided in the url.
        if (! $request->route()->parameter('company_key') && Ninja::isSelfHost()) {
            $company = Account::query()->first()->default_company ?? Account::query()->first()->companies->first();

            if (! $company->client_can_register) {
                abort(400, 'Registration disabled');
            }

            //$request->merge(['key' => $company->company_key]);
            session()->put('company_key', $company->company_key);

            return $next($request);
        }

        abort(404, 'ContactRegister Middleware');
    }
}
