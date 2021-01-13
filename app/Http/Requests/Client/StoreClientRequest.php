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

namespace App\Http\Requests\Client;

use App\DataMapper\ClientSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Ninja\CanStoreClientsRule;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Models\Client;
use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;

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
                $rules['documents.'.$index] = 'file|mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
            }
        } elseif ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:20000';
        }

        /* Ensure we have a client name, and that all emails are unique*/
        //$rules['name'] = 'required|min:1';
        $rules['id_number'] = 'unique:clients,id_number,'.$this->id.',id,company_id,'.$this->company_id;
        $rules['settings'] = new ValidClientGroupSettingsRule();
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

        if (auth()->user()->company()->account->isFreeHostedClient()) {
            $rules['hosted_clients'] = new CanStoreClientsRule($this->company_id);
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        //@todo implement feature permissions for > 100 clients
        
        $settings = ClientSettings::defaults();

        if (array_key_exists('settings', $input) && ! empty($input['settings'])) {
            foreach ($input['settings'] as $key => $value) {
                $settings->{$key} = $value;
            }
        }

        $input = $this->decodePrimaryKeys($input);


        //is no settings->currency_id is set then lets dive in and find either a group or company currency all the below may be redundant!!
        if (! property_exists($settings, 'currency_id') && isset($input['group_settings_id'])) {
            $input['group_settings_id'] = $this->decodePrimaryKey($input['group_settings_id']);
            $group_settings = GroupSetting::find($input['group_settings_id']);

            if ($group_settings && property_exists($group_settings->settings, 'currency_id') && isset($group_settings->settings->currency_id)) {
                $settings->currency_id = (string) $group_settings->settings->currency_id;
            } else {
                $settings->currency_id = (string) auth()->user()->company()->settings->currency_id;
            }
        } elseif (! property_exists($settings, 'currency_id')) {
            $settings->currency_id = (string) auth()->user()->company()->settings->currency_id;
        }

        if (isset($input['currency_code'])) {
            $settings->currency_id = $this->getCurrencyCode($input['currency_code']);
        }

        $input['settings'] = $settings;

        if (isset($input['country_code'])) {
            $input['country_id'] = $this->getCountryCode($input['country_code']);
        }

        if (isset($input['shipping_country_code'])) {
            $input['shipping_country_id'] = $this->getCountryCode($input['shipping_country_code']);
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'unique' => ctrans('validation.unique', ['attribute' => 'email']),
            //'required' => trans('validation.required', ['attribute' => 'email']),
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
        ];
    }

    private function getCountryCode($country_code)
    {
        $countries = Cache::get('countries');

        $country = $countries->filter(function ($item) use ($country_code) {
            return $item->iso_3166_2 == $country_code || $item->iso_3166_3 == $country_code;
        })->first();

        return (string) $country->id;
    }

    private function getCurrencyCode($code)
    {
        $currencies = Cache::get('currencies');

        $currency = $currencies->filter(function ($item) use ($code) {
            return $item->code == $code;
        })->first();

        return (string) $currency->id;
    }
}
