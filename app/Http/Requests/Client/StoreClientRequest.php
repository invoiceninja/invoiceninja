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

namespace App\Http\Requests\Client;

use App\DataMapper\ClientSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Client\CountryCodeExistsRule;
use App\Http\ValidationRules\Ninja\CanStoreClientsRule;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Models\Client;
use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class StoreClientRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', Client::class);
    }

    public function rules()
    {
        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.'.$index] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('clients')->where('company_id', auth()->user()->company()->id);
        }

        $rules['country_id'] = 'integer|nullable';

        if (isset($this->currency_code)) {
            $rules['currency_code'] = 'sometimes|exists:currencies,code';
        }

        if (isset($this->country_code)) {
            $rules['country_code'] = new CountryCodeExistsRule();
        }

        /* Ensure we have a client name, and that all emails are unique*/
        //$rules['name'] = 'required|min:1';
        $rules['settings'] = new ValidClientGroupSettingsRule();
        $rules['contacts'] = 'array';
        $rules['contacts.*.email'] = 'bail|nullable|distinct|sometimes|email';
        $rules['contacts.*.password'] = [
            'bail',
            'nullable',
            'sometimes',
            'string',
            'min:7',             // must be at least 10 characters in length
            'regex:/[a-z]/',      // must contain at least one lowercase letter
            'regex:/[A-Z]/',      // must contain at least one uppercase letter
            'regex:/[0-9]/',      // must contain at least one digit
            //'regex:/[@$!%*#?&.]/', // must contain a special character
        ];

        if (auth()->user()->company()->account->isFreeHostedClient()) {
            $rules['id'] = new CanStoreClientsRule(auth()->user()->company()->id);
        }

        $rules['number'] = ['bail', 'nullable', Rule::unique('clients')->where('company_id', auth()->user()->company()->id)];
        $rules['id_number'] = ['bail', 'nullable', Rule::unique('clients')->where('company_id', auth()->user()->company()->id)];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        /* Default settings */
        $settings = (array)ClientSettings::defaults();

        /* Stub settings if they don't exist */
        if(!array_key_exists('settings', $input))
            $input['settings'] = [];
        elseif(is_object($input['settings']))
            $input['settings'] = (array)$input['settings'];
        
        /* Merge default into base settings */
        $input['settings'] = array_merge($input['settings'], $settings);

        /* Type and property enforcement */
        foreach ($input['settings'] as $key => $value) 
        {
            if ($key == 'default_task_rate') {
                $value = floatval($value);
                $input['settings'][$key] = $value;
            }

            if($key == 'translations')
                unset($input['settings']['translations']);
        }

        /* Convert hashed IDs to IDs*/
        $input = $this->decodePrimaryKeys($input);

        //is no settings->currency_id is set then lets dive in and find either a group or company currency all the below may be redundant!!
        if (! array_key_exists('currency_id', $input['settings']) && isset($input['group_settings_id'])) {
            $group_settings = GroupSetting::find($input['group_settings_id']);

            if ($group_settings && property_exists($group_settings->settings, 'currency_id') && isset($group_settings->settings->currency_id)) {
                $input['settings']['currency_id'] = (string) $group_settings->settings->currency_id;
            } else {
                $input['settings']['currency_id'] = (string) auth()->user()->company()->settings->currency_id;
            }

        } elseif (! array_key_exists('currency_id', $input['settings'])) {
            $input['settings']['currency_id'] = (string) auth()->user()->company()->settings->currency_id;
        }

        if (isset($input['currency_code'])) {
            $input['settings']['currency_id'] = $this->getCurrencyCode($input['currency_code']);
        }

        if (isset($input['language_code'])) {
            $input['settings']['language_id'] = $this->getLanguageId($input['language_code']);

            if(strlen($input['settings']['language_id']) == 0)
                unset($input['settings']['language_id']);
        }

        if (isset($input['country_code'])) {
            $input['country_id'] = $this->getCountryCode($input['country_code']);
        }

        if (isset($input['shipping_country_code'])) {
            $input['shipping_country_id'] = $this->getCountryCode($input['shipping_country_code']);
        }

        /* If there is a client number, just unset it here. */
        if (array_key_exists('number', $input) && (is_null($input['number']) || empty($input['number']))) {
            unset($input['number']);
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            // 'unique' => ctrans('validation.unique', ['attribute' => ['email','number']),
            //'required' => trans('validation.required', ['attribute' => 'email']),
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
            'currency_code' => 'Currency code does not exist',
        ];
    }

    private function getLanguageId($language_code)
    {
        $languages = Cache::get('languages');

        $language = $languages->filter(function ($item) use ($language_code) {
            return $item->locale == $language_code;
        })->first();

        if ($language) {
            return (string) $language->id;
        }

        return '';
    }

    private function getCountryCode($country_code)
    {
        $countries = Cache::get('countries');

        $country = $countries->filter(function ($item) use ($country_code) {
            return $item->iso_3166_2 == $country_code || $item->iso_3166_3 == $country_code;
        })->first();

        if ($country) {
            return (string) $country->id;
        }

        return '';
    }

    private function getCurrencyCode($code)
    {
        $currencies = Cache::get('currencies');

        $currency = $currencies->filter(function ($item) use ($code) {
            return $item->code == $code;
        })->first();

        if ($currency) {
            return (string) $currency->id;
        }

        return '';
    }
}
