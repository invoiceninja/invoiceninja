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

namespace App\Http\Requests\GroupSetting;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\Settings\SettingsData;
use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Models\Account;
use App\Models\GroupSetting;

class StoreGroupSettingRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', GroupSetting::class) && $user->account->hasFeature(Account::FEATURE_API);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules['name'] = 'required|unique:group_settings,name,null,null,company_id,'.$user->companyId();

        $rules['settings'] = new ValidClientGroupSettingsRule();

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('settings', $input)) {
            $input['settings'] = $this->filterSaveableSettings($input['settings']);
        } else {
            $input['settings'] = (array)ClientSettings::defaults();
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'settings' => 'settings must be a valid json structure',
        ];
    }

    /**
     * For the hosted platform, we restrict the feature settings.
     *
     * This method will trim the company settings object
     * down to the free plan setting properties which
     * are saveable
     *
     * @param  object $settings
     * @return array $settings
     */
    private function filterSaveableSettings($settings)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $settings_data = new SettingsData();
        $settings = $settings_data->cast($settings)->toObject();

        if (! $user->account->isFreeHostedClient()) {
            return (array)$settings;
        }

        $saveable_casts = CompanySettings::$free_plan_casts;

        foreach ($settings as $key => $value) {
            if (! array_key_exists($key, $saveable_casts)) {
                unset($settings->{$key});
            }
        }

        return (array)$settings;
    }

}
