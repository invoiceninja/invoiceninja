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
use App\Utils\Traits\MakesHash;

class GroupSettingRepository extends BaseRepository
{
    use MakesHash;

    /**
     * Gets the class name.
     *
     * @return     string  The class name.
     */
    public function getClassName()
    {
        return GroupSetting::class;
    }

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
