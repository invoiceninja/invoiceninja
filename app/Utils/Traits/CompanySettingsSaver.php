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

		$settings = $this->checkSettingType($settings, CompanySettings::$casts);

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

			/*Separate loop if it is a _id field which is an integer cast as a string*/
			if(substr($key, -3) == '_id' || substr($key, -8) == '_counter'){
				$value = "integer";
				
				if($this->checkAttribute($value, $settings->{$key})){
					\Log::error("System says true {$key} a {$value} = ".$settings->{$key});
				}
				else {
					\Log::error('popping '.$key.' '.$value.' '.$settings->{$key}.' off the stack');
					unset($settings->{$key});
				}

				continue;
			}

			/* Handles unset settings or blank strings */
			if(is_null($settings->{$key}) || !isset($settings->{$key}) || $settings->{$key} == ''){

				continue;
			}

			/*Catch all filter */
			if($this->checkAttribute($value, $settings->{$key})){
			}
			else {
				unset($settings->{$key});
			}

		}
		\Log::error(print_r($settings,1));
		return $settings;
	}
	

	private function checkAttribute($key, $value)
	{
		switch ($key)
		{
			case 'int':
			case 'integer':
				//return is_int($value);
				//return  strval($value) === strval(intval($value)) ;
				return ctype_digit(strval($value));
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