<?php

namespace App\Http\Requests\ClientPortal;

use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->company()->client_registration_fields as $field) {
            if ($field['required']) {
                $rules[$field['key']] = ['required'];
            }
        }

        foreach ($rules as $field => $properties) {
            if ($field === 'email') {
                $rules[$field] = array_merge($rules[$field], ['email:rfc,dns', 'max:255']);
            }

            if ($field === 'current_password') {
                $rules[$field] = array_merge($rules[$field], ['string', 'min:6', 'confirmed']);
            }
        }

        if ($this->company()->settings->client_portal_terms || $this->company()->settings->client_portal_privacy_policy) {
            $rules['terms'] = ['required'];
        }

        return $rules;
    }

    public function company()
    {

        //this should be all we need, the rest SHOULD be redundant because of our Middleware
        if ($this->key) {
            return Company::where('company_key', $this->key)->first();
        }

        if ($this->company_key) {
            return Company::where('company_key', $this->company_key)->firstOrFail();
        }

        if (! $this->route()->parameter('company_key') && Ninja::isSelfHost()) {
            $company = Account::first()->default_company;

            if (! $company->client_can_register) {
                abort(403, 'This page is restricted');
            }

            return $company;
        }

        if (Ninja::isHosted()) {
            $subdomain = explode('.', $this->getHost())[0];

            $query = [
                'subdomain' => $subdomain,
                'portal_mode' => 'subdomain',
            ];

            if ($company = MultiDB::findAndSetDbByDomain($query)) {
                return $company;
            }

            $query = [
                'portal_domain' => $this->getSchemeAndHttpHost(),
                'portal_mode' => 'domain',
            ];

            if ($company = MultiDB::findAndSetDbByDomain($query)) {
                return $company;
            }
        }

        abort(400, 'Register request not found.');
    }
}
