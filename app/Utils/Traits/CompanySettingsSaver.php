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

namespace App\Utils\Traits;

use App\DataMapper\CompanySettings;

/**
 * Class CompanySettingsSaver
 * @package App\Utils\Traits
 */
trait CompanySettingsSaver
{

	public function saveSettings($settings)
	{
		if(!$settings)
			return;
		
		\Log::error(print_r($settings));
		$company_settings = $this->settings;

		//unset protected properties.
		foreach(CompanySettings::$protected_fields as $field)
			unset($settings[$field]);

		//iterate through set properties with new values;
		foreach($settings as $key => $value)
			$company_settings->{$key} = $value;

		$this->settings = $company_settings;
		$this->save();
	}

}