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

namespace App\Http\Requests\Company;

use App\DataMapper\CompanySettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Company\ValidCompanyQuantity;
use App\Http\ValidationRules\Company\ValidSubdomain;
use App\Http\ValidationRules\ValidSettingsRule;
use App\Models\Company;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

class StoreCompanyRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Company::class);
    }

    public function rules()
    {
        $input = $this->all();

        $rules = [];

        $rules['name'] = new ValidCompanyQuantity();
        $rules['company_logo'] = 'mimes:jpeg,jpg,png,gif|max:10000'; // max 10000kb
        $rules['settings'] = new ValidSettingsRule();

        if (isset($input['portal_mode']) && ($input['portal_mode'] == 'domain' || $input['portal_mode'] == 'iframe')) {
            $rules['portal_domain'] = 'sometimes|url';
        } else {
           
            if(Ninja::isHosted()){
                $rules['subdomain'] = ['nullable', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9.-]+[a-zA-Z0-9]$/', new ValidSubdomain($this->all())];
            }
            else
                $rules['subdomain'] = 'nullable|alpha_num';
           
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('google_analytics_url', $input)) {
            $input['google_analytics_key'] = $input['google_analytics_url'];
        }

        $company_settings = CompanySettings::defaults();

        //@todo this code doesn't make sense as we never return $company_settings anywhere
        //@deprecated???
        if (array_key_exists('settings', $input) && ! empty($input['settings'])) {
            foreach ($input['settings'] as $key => $value) {
                $company_settings->{$key} = $value;
            }
        }

        if(array_key_exists('portal_domain', $input))
            $input['portal_domain'] = strtolower($input['portal_domain']);

        $this->replace($input);
    }
}
