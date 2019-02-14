<?php

namespace App\DataMapper;

/**
 * ClientSettings
 */
class ClientSettings 
{
	/**
	 * @return \stdClass
	 *
	 * 
			timezone_id
			language_id
			currency_id
     */
	public static function defaults() : \stdClass
	{

		return (object)[];

	}
}

