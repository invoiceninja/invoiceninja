<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\GroupSetting;

class GroupSettingRepository extends BaseRepository
{

    public function save($data, GroupSetting $group_setting) :?GroupSetting
    {
        $group_setting->fill($data);
        $group_setting->save();

        if (array_key_exists('company_logo', $data) && $data['company_logo'] == '') {
            $settings = $group_setting->settings;
            unset($settings->company_logo);
            $group_setting->settings = $settings;
            $group_setting->save();
        }

        return $group_setting;
    }
}
