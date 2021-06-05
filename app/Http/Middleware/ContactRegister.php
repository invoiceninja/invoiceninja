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

        if (strpos($request->getHost(), 'invoicing.co') !== false) 
        {
            $subdomain = explode('.', $request->getHost())[0];
            
            $query = [
                'subdomain' => $subdomain,
                'portal_mode' => 'subdomain',
            ];

            $company = Company::where($query)->first();

            if($company)
            {
                abort_unless($company->client_can_register, 404);

                $request->merge(['key' => $company->company_key]);

                return $next($request);
            }

        }

       $query = [
            'portal_domain' => $request->getSchemeAndHttpHost(),
            'portal_mode' => 'domain',
        ];

        if($company = Company::where($query)->first())
        {
            abort_unless($company->client_can_register, 404);

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

        abort(404, 'ContactRegister Middlware');
    }
}
