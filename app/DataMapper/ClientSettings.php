<?php

namespace App\DataMapper;

/**
 * ClientSettings
 */
class ClientSettings extends BaseSettings
{
	public $timezone_id;
	public $language_id;
	public $currency_id;
	
	/**
	 * @return \stdClass
	 *
     */
	public static function defaults() : \stdClass
	{

		return (object)[
			'timezone_id' => NULL,
			'language_id' => NULL,
			'currency_id' => NULL,
			'payment_terms' => NULL,
		];

	}


}

