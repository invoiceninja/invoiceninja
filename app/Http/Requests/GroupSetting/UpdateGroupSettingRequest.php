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

use App\DataMapper\CompanySettings;
use App\DataMapper\Settings\SettingsData;
use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidClientGroupSettingsRule;

class UpdateGroupSettingRequest extends Request
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

        return $user->can('edit', $this->group_setting);
    }

    public function rules()
    {

        return [
            'settings' => [new ValidClientGroupSettingsRule()],
        ];

    }

    public function prepareForValidation()
    {
        $input = $this->all();

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
