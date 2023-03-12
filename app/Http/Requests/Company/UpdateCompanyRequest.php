<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
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

    private array $protected_input = [
        'client_portal_privacy_policy',
        'client_portal_terms',
        'portal_custom_footer',
        'portal_custom_css',
        'portal_custom_head'
    ];

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
        $rules['matomo_id'] = 'nullable|integer';
        
        // $rules['client_registration_fields'] = 'array';

        if (isset($input['portal_mode']) && ($input['portal_mode'] == 'domain' || $input['portal_mode'] == 'iframe')) {
            $rules['portal_domain'] = 'sometimes|url';
        } else {
            if (Ninja::isHosted()) {
                $rules['subdomain'] = ['nullable', 'regex:/^[a-zA-Z0-9.-]+[a-zA-Z0-9]$/', new ValidSubdomain($this->all())];
            } else {
                $rules['subdomain'] = 'nullable|alpha_num';
            }
        }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('portal_domain', $input) && strlen($input['portal_domain']) > 1) {
            $input['portal_domain'] = $this->addScheme($input['portal_domain']);
            $input['portal_domain'] = rtrim(strtolower($input['portal_domain']), "/");
        }

        if (array_key_exists('settings', $input)) {
            $input['settings'] = (array)$this->filterSaveableSettings($input['settings']);
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
     * @return \stdClass $settings
     */
    private function filterSaveableSettings($settings)
    {
        $account = $this->company->account;

        if (Ninja::isHosted()) {
            foreach ($this->protected_input as $protected_var) {
                $settings[$protected_var] = str_replace("script", "", $settings[$protected_var]);
            }
        }

        if (isset($settings['email_style_custom'])) {
            $settings['email_style_custom'] = str_replace(['{{','}}'], ['',''], $settings['email_style_custom']);
        }

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

    private function addScheme($url, $scheme = 'https://')
    {
        if (Ninja::isHosted()) {
            $url = str_replace('http://', '', $url);
            $url = parse_url($url, PHP_URL_SCHEME) === null ? $scheme.$url : $url;
        }

        return rtrim($url, '/');
    }
}
