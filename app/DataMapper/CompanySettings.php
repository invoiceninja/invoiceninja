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
	public static function defaults() : \stdClass
	{
		$config = json_decode(config('ninja.settings'));

		return (object)[
			'timezone_id' => $config->timezone_id,
			'language_id' => $config->language_id,
			'currency_id' => $config->currency_id,
			'custom' => (object) [
				'label1' => NULL,
				'value1' => NULL,
				'label2' => NULL,
				'value2' => NULL,
				'label3' => NULL,
				'value3' => NULL,
				'label4' => NULL,
				'value5' => NULL,
			],
			'client' => (object) [
				'label1' => NULL,
				'label2' => NULL,
				'label3' => NULL,
				'label4' => NULL,				
			],
			'contact' => (object) [
				'label1' => NULL,
				'label2' => NULL,
				'label3' => NULL,
				'label4' => NULL,				
			],
			'invoice' => (object) [
				'label1' => NULL,
				'label2' => NULL,
				'label3' => NULL,
				'label4' => NULL,				
			],
			'product' => (object) [
				'label1' => NULL,
				'label2' => NULL,
				'label3' => NULL,
				'label4' => NULL,				
			],
			'task' => (object) [
				'label1' => NULL,
				'label2' => NULL,
				'label3' => NULL,
				'label4' => NULL,				
			],
			'expense' => (object) [
				'label1' => NULL,
				'label2' => NULL,
				'label3' => NULL,
				'label4' => NULL,				
			],
			'translations' => (object) [],
		];

	}
}

