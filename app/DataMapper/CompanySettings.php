<?php

namespace App\DataMapper;

/**
 * CompanySettings
 */
class CompanySettings 
{
	/**
	 * @return \stdClass
     */
	public static function default() : \stdClass
	{
		$config = json_decode(config('ninja.settings'));

		return (object)[
			'timezone_id' => $config->timezone_id,
			'language_id' => $config->language_id,
			'currency_id' => $config->currency_id,
			'custom' => (object) [
				'label1' => '',
				'value1' => '',
				'label2' => '',
				'value2' => '',
				'label3' => '',
				'value3' => '',
				'label4' => '',
				'value5' => '',
			],
			'client' => (object) [
				'label1' => '',
				'label2' => '',
				'label3' => '',
				'label4' => '',				
			],
			'contact' => (object) [
				'label1' => '',
				'label2' => '',
				'label3' => '',
				'label4' => '',				
			],
			'invoice' => (object) [
				'label1' => '',
				'label2' => '',
				'label3' => '',
				'label4' => '',				
			],
			'product' => (object) [
				'label1' => '',
				'label2' => '',
				'label3' => '',
				'label4' => '',				
			],
			'task' => (object) [
				'label1' => '',
				'label2' => '',
				'label3' => '',
				'label4' => '',				
			],
			'expense' => (object) [
				'label1' => '',
				'label2' => '',
				'label3' => '',
				'label4' => '',				
			],
			'translations' => (object) [],
		];

	}
}

