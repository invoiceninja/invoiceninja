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

use App\DataMapper\CompanySettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->client);
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/

        if ($this->input('documents') && is_array($this->input('documents'))) {
            $documents = count($this->input('documents'));

            foreach (range(0, $documents) as $index) {
                $rules['documents.'.$index] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        $rules['company_logo'] = 'mimes:jpeg,jpg,png,gif|max:10000';
        $rules['industry_id'] = 'integer|nullable';
        $rules['size_id'] = 'integer|nullable';
        $rules['country_id'] = 'integer|nullable';
        $rules['shipping_country_id'] = 'integer|nullable';
        //$rules['id_number'] = 'unique:clients,id_number,,id,company_id,' . auth()->user()->company()->id;
        //$rules['id_number'] = 'unique:clients,id_number,'.$this->id.',id,company_id,'.$this->company_id;

        if ($this->id_number) {
            $rules['id_number'] = Rule::unique('clients')->where('company_id', auth()->user()->company()->id)->ignore($this->client->id);
        }

        if ($this->number) {
            $rules['number'] = Rule::unique('clients')->where('company_id', auth()->user()->company()->id)->ignore($this->client->id);
        }

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

        if (isset($input['group_settings_id'])) {
            $input['group_settings_id'] = $this->decodePrimaryKey($input['group_settings_id']);
        }

        /* If the user removes the currency we must always set the default */
        if (array_key_exists('settings', $input) && ! array_key_exists('currency_id', $input['settings'])) {
            $input['settings']['currency_id'] = (string) auth()->user()->company()->settings->currency_id;
        }

        if (isset($input['language_code'])) {
            $input['settings']['language_id'] = $this->getLanguageId($input['language_code']);
        }

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('settings', $input)) {
            $input['settings'] = $this->filterSaveableSettings($input['settings']);
        }

        $this->replace($input);
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
