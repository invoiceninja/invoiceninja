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
	public $default_task_rate;
	public $send_reminders;

	
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

