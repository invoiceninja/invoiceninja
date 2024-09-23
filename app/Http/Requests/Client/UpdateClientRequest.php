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

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;
use App\DataMapper\CompanySettings;
use Illuminate\Support\Facades\Cache;
use App\Utils\Traits\ChecksEntityStatus;
use App\Http\ValidationRules\EInvoice\ValidClientScheme;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;

class UpdateClientRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->client);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/
        /** @var  \App\Models\User $user */
        $user = auth()->user();

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->fileValidation();
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->fileValidation();
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        } else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        $rules['company_logo'] = 'mimes:jpeg,jpg,png,gif|max:10000';
        $rules['industry_id'] = 'integer|nullable';
        $rules['size_id'] = 'integer|nullable';
        $rules['country_id'] = 'integer|nullable|exists:countries,id';
        $rules['shipping_country_id'] = 'integer|nullable|exists:countries,id';
        $rules['classification'] = 'bail|sometimes|nullable|in:individual,business,company,partnership,trust,charity,government,other';
        $rules['id_number'] = ['sometimes', 'bail', 'nullable', Rule::unique('clients')->where('company_id', $user->company()->id)->ignore($this->client->id)];
        $rules['number'] = ['sometimes', 'bail', Rule::unique('clients')->where('company_id', $user->company()->id)->ignore($this->client->id)];

        $rules['e_invoice'] = ['sometimes','nullable', new ValidClientScheme()];

        $rules['settings'] = new ValidClientGroupSettingsRule();
        $rules['contacts'] = 'array';
        $rules['contacts.*.email'] = 'bail|nullable|distinct|sometimes|email';
        $rules['contacts.*.password'] = [
            'nullable',
            'sometimes',
            'string',
            'min:7',             // must be at least 10 characters in length
            'regex:/[a-z]/',      // must contain at least one lowercase letter
            'regex:/[A-Z]/',      // must contain at least one uppercase letter
            'regex:/[0-9]/',      // must contain at least one digit
            //'regex:/[@$!%*#?&.]/', // must contain a special character
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'email' => ctrans('validation.email', ['attribute' => 'email']),
            'name.required' => ctrans('validation.required', ['attribute' => 'name']),
            'required' => ctrans('validation.required', ['attribute' => 'email']),
            'contacts.*.password.min' => ctrans('texts.password_strength'),
            'contacts.*.password.regex' => ctrans('texts.password_strength'),
            'contacts.*.password.string' => ctrans('texts.password_strength'),
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        /** @var  \App\Models\User $user */
        $user = auth()->user();

        /* If the user removes the currency we must always set the default */
        if (array_key_exists('settings', $input) && ! array_key_exists('currency_id', $input['settings'])) {
            $input['settings']['currency_id'] = (string) $user->company()->settings->currency_id;
        } elseif (empty($input['settings']['currency_id']) ?? true) {
            $input['settings']['currency_id'] = (string) $user->company()->settings->currency_id;
        }

        if (isset($input['language_code'])) {
            $input['settings']['language_id'] = $this->getLanguageId($input['language_code']);
        }

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('settings', $input)) {
            $input['settings'] = $this->filterSaveableSettings($input['settings']);
        }

        if (array_key_exists('name', $input)) {
            $input['name'] = strip_tags($input['name']);
        }

        // allow setting country_id by iso code
        if (isset($input['country_code'])) {
            $input['country_id'] = $this->getCountryCode($input['country_code']);
        }

        // allow setting country_id by iso code
        if (isset($input['shipping_country_code'])) {
            $input['shipping_country_id'] = $this->getCountryCode($input['shipping_country_code']);
        }

        if (isset($input['e_invoice']) && is_array($input['e_invoice'])) {
            //ensure it is normalized first!
            $input['e_invoice'] = $this->client->filterNullsRecursive($input['e_invoice']);
        }

        $this->replace($input);
    }

    private function getCountryCode($country_code)
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        $country = $countries->first(function ($item) use ($country_code) {
            return $item->iso_3166_2 == $country_code || $item->iso_3166_3 == $country_code;
        });

        return $country ? (string) $country->id : '';
    }

    private function getLanguageId($language_code)
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Language> */
        $languages = app('languages');

        $language = $languages->first(function ($item) use ($language_code) {
            return $item->locale == $language_code;
        });

        return $language ? (string) $language->id : '';
    }

    /**
     * For the hosted platform, we restrict the feature settings.
     *
     * This method will trim the company settings object
     * down to the free plan setting properties which
     * are saveable
     *
     * @param  mixed $settings
     * @return \stdClass $settings
     */
    private function filterSaveableSettings($settings)
    {
        $account = $this->client->company->account;

        if (! $account->isFreeHostedClient()) {
            return $settings;
        }

        $saveable_casts = CompanySettings::$free_plan_casts;

        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, $saveable_casts)) {
                unset($settings->{$key});
            }

            //26-04-2022 - In case settings are returned as array instead of object
            if ($key == 'default_task_rate' && is_array($settings)) {
                $settings['default_task_rate'] = floatval($value);
            } elseif ($key == 'default_task_rate' && is_object($settings)) {
                $settings->default_task_rate = floatval($value);
            }
        }

        return $settings;
    }
}
