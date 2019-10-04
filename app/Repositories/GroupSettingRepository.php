<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\GroupSetting;
use App\Utils\Traits\MakesHash;

class GroupSettingRepository
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

		return $group_setting;
	}

}