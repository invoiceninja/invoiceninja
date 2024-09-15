<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Client;

use App\DataMapper\ClientSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Ninja\CanStoreClientsRule;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Models\Client;
use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreClientRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var  \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', Client::class);
    }

    public function rules()
    {
        /** @var  \App\Models\User $user */
        $user = auth()->user();

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->fileValidation();
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->fileValidation();
        } else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        }

        /* Ensure we have a client name, and that all emails are unique*/
        //$rules['name'] = 'required|min:1';
        $rules['settings'] = new ValidClientGroupSettingsRule();
        $rules['contacts'] = 'bail|array';
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

        if ($user->company()->account->isFreeHostedClient()) {
            $rules['id'] = new CanStoreClientsRule($user->company()->id);
        }

        $rules['number'] = ['bail', 'nullable', Rule::unique('clients')->where('company_id', $user->company()->id)];
        $rules['id_number'] = ['bail', 'nullable', Rule::unique('clients')->where('company_id', $user->company()->id)];
        $rules['classification'] = 'bail|sometimes|nullable|in:individual,business,company,partnership,trust,charity,government,other';
        $rules['shipping_country_id'] = 'integer|nullable|exists:countries,id';
        $rules['number'] = ['sometimes', 'nullable', 'bail', Rule::unique('clients')->where('company_id', $user->company()->id)];
        $rules['country_id'] = 'integer|nullable|exists:countries,id';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /* Default settings */
        $settings = (array)ClientSettings::defaults();

        /* Stub settings if they don't exist */
        if (!array_key_exists('settings', $input)) {
            $input['settings'] = [];
        } elseif (is_object($input['settings'])) {
            $input['settings'] = (array)$input['settings'];
        }

        /* Merge default into base settings */
        $input['settings'] = array_merge($input['settings'], $settings);

        /* Type and property enforcement */
        foreach ($input['settings'] as $key => $value) {
            if ($key == 'default_task_rate') {
                $value = floatval($value);
                $input['settings'][$key] = $value;
            }

            if ($key == 'translations') {
                unset($input['settings']['translations']);
            }
        }

        /* Convert hashed IDs to IDs*/
        $input = $this->decodePrimaryKeys($input);

        //is no settings->currency_id is set then lets dive in and find either a group or company currency all the below may be redundant!!
        if (! array_key_exists('currency_id', $input['settings']) && isset($input['group_settings_id'])) {
            $group_settings = GroupSetting::find($input['group_settings_id']);

            if ($group_settings && property_exists($group_settings->settings, 'currency_id') && is_numeric($group_settings->settings->currency_id)) {
                $input['settings']['currency_id'] = (string) $group_settings->settings->currency_id;
            } else {
                $input['settings']['currency_id'] = (string) $user->company()->settings->currency_id;
            }
        } elseif (! array_key_exists('currency_id', $input['settings'])) {
            $input['settings']['currency_id'] = (string) $user->company()->settings->currency_id;
        } elseif (empty($input['settings']['currency_id']) ?? true) {
            $input['settings']['currency_id'] = (string) $user->company()->settings->currency_id;
        }

        if (isset($input['currency_code'])) {
            $input['settings']['currency_id'] = $this->getCurrencyCode($input['currency_code']);
        }

        if (isset($input['language_code'])) {
            $input['settings']['language_id'] = $this->getLanguageId($input['language_code']);

            if (strlen($input['settings']['language_id']) == 0) {
                unset($input['settings']['language_id']);
            }
        }


        // allow setting country_id by iso code
        if (isset($input['country_code'])) {
            $input['country_id'] = $this->getCountryCode($input['country_code']);
        }

        // allow setting country_id by iso code
        if (isset($input['shipping_country_code'])) {
            $input['shipping_country_id'] = $this->getCountryCode($input['shipping_country_code']);
        }

        /* If there is a client number, just unset it here. */
        if (array_key_exists('number', $input) && (is_null($input['number']) || empty($input['number']))) {
            unset($input['number']);
        }

        // prevent xss injection
        if (array_key_exists('name', $input)) {
            $input['name'] = strip_tags($input['name']);
        }

        //If you want to validate, the prop must be set.
        $input['id'] = null;

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
            'currency_code' => 'Currency code does not exist',
        ];
    }

    private function getLanguageId(string $language_code)
    {
        /** @var \Illuminate\Support\Collection<\App\Models\Language> */
        $languages = app('languages');

        $language = $languages->first(function ($item) use ($language_code) {
            return $item->locale == $language_code;
        });

        return $language ? (string)$language->id : '';

    }

    private function getCountryCode(string $country_code)
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        $country = $countries->first(function ($item) use ($country_code) {
            return $item->iso_3166_2 == $country_code || $item->iso_3166_3 == $country_code;
        });

        return $country ? (string) $country->id : '';

    }

    private function getCurrencyCode($code)
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $currencies = app('currencies');

        $currency = $currencies->first(function ($item) use ($code) {
            return $item->code == $code;
        });

        return  $currency ? (string)$currency->id : '';

    }
}
