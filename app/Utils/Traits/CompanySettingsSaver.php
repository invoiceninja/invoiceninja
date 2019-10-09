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

		$company_settings = $this->settings;

		//unset protected properties.
		foreach(CompanySettings::$protected_fields as $field)
			unset($settings[$field]);

		//make sure the inbound settings have the correct casts!
		//$settings = CompanySettings::setCasts($settings, CompanySettings::$casts);

//todo checks are here
//		$settings = $this->checkSettingType($settings, CompanySettings::$casts);

		//iterate through set properties with new values;
		foreach($settings as $key => $value)
			$company_settings->{$key} = $value;

		$this->settings = $company_settings;
		$this->save();
	}


	private function checkSettingType($settings, $casts)
	{
		$settings = (object)$settings;

		foreach ($casts as $key => $value){

		\Log::error("the gettype of {$key} = ". gettype($settings->{$key}));

			if(substr($key, -3) == '_id'){
				$value = "integer";
				
				if($this->checkAttribute($value, (int)$settings->{$key})){
					//throw new \Exception($settings->{$key}. " " . $key . " is not type ". $value);
					\Log::error($settings->{$key}. " " . $key . " is type ". $value);
				}
				else {
					\Log::error($settings->{$key}. " " . $key . " is nottype ". $value);
				}
				continue;
			}

			if(is_null($settings->{$key}) || !isset($settings->{$key}) || $settings->{$key} == ''){
				\Log::error("skipping ".$settings->{$key}. " " . $key . " is type ". $value);

				continue;
			}

			\Log::error("checking ".$settings->{$key}. " " . $key . " is type ". $value);

			if($this->checkAttribute($value, $settings->{$key})){
				//throw new \Exception($settings->{$key}. " " . $key . " is not type ". $value);
				\Log::error($settings->{$key}. " " . $key . " is type ". $value);
			}
			else {
				\Log::error($settings->{$key}. " " . $key . " is nottype ". $value);
			}

		}
	}
	

	private function checkAttribute($key, $value)
	{
		switch ($key)
		{
			case 'int':
			case 'integer':
				return is_int($value);
			case 'real':
			case 'float':
			case 'double':
				return is_float($value);
			case 'string':
				return method_exists($value, '__toString' ) || is_null($value) || is_string($value);
			case 'bool':
			case 'boolean':
				return is_bool($value);
			case 'object':
				return is_object($value);
			case 'array':
				return is_array($value);
			case 'json':
				json_decode($string);
 					return (json_last_error() == JSON_ERROR_NONE);
			default:
				return $value;
		}
	}










}