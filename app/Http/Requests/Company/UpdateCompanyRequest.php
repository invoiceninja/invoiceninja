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

namespace App\Http\Requests\Company;

use App\DataMapper\CompanySettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Company\ValidSubdomain;
use App\Http\ValidationRules\ValidSettingsRule;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

class UpdateCompanyRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->company);
    }

    public function rules()
    {
        $input = $this->all();
        
        $rules = [];

        $rules['company_logo'] = 'mimes:jpeg,jpg,png,gif|max:10000'; // max 10000kb
        $rules['settings'] = new ValidSettingsRule();
        $rules['industry_id'] = 'integer|nullable';
        $rules['size_id'] = 'integer|nullable';
        $rules['country_id'] = 'integer|nullable';
        $rules['work_email'] = 'email|nullable';

        if (isset($input['portal_mode']) && ($input['portal_mode'] == 'domain' || $input['portal_mode'] == 'iframe')) {
            $rules['portal_domain'] = 'sometimes|url';
        } else {

            if(Ninja::isHosted()){
                $rules['subdomain'] = ['nullable', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9.-]+[a-zA-Z0-9]$/', new ValidSubdomain($this->all())];
            }
            else
                $rules['subdomain'] = 'nullable|alpha_num';
        }

        // if($this->company->account->isPaidHostedClient()) {
        //     return $settings;
        // }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        // if(array_key_exists('portal_domain', $input) && strlen($input['portal_domain']) > 1)
        //     $input['portal_domain'] = str_replace("http:", "https:", $input['portal_domain']);

        if (array_key_exists('settings', $input)) {
            $input['settings'] = $this->filterSaveableSettings($input['settings']);
        }

        $this->replace($input);
    }

    /**
     * For the hosted platform, we restrict the feature settings.
     *
     * This method will trim the company settings object
     * down to the free plan setting properties which
     * are saveable
     *
     * @param  object $settings
     * @return stdClass $settings
     */
    private function filterSaveableSettings($settings)
    {
        $account = $this->company->account;

        if (! $account->isFreeHostedClient()) {
            return $settings;
        }

        $saveable_casts = CompanySettings::$free_plan_casts;

        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, $saveable_casts)) {
                unset($settings->{$key});
            }
        }

        return $settings;
    }
}
