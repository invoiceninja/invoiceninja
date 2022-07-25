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

namespace App\Http\Requests\GroupSetting;

use App\DataMapper\ClientSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Models\GroupSetting;

class StoreGroupSettingRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', GroupSetting::class);
    }

    public function rules()
    {
        $rules['name'] = 'required|unique:group_settings,name,null,null,company_id,'.auth()->user()->companyId();

        $rules['settings'] = new ValidClientGroupSettingsRule();

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $group_settings = ClientSettings::defaults();

        if (array_key_exists('settings', $input) && ! empty($input['settings'])) {
            foreach ($input['settings'] as $key => $value) {
                $group_settings->{$key} = $value;
            }
        }

        $input['settings'] = $group_settings;

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'settings' => 'settings must be a valid json structure',
        ];
    }
}
