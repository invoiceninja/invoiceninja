<?php

namespace App\DataMapper;

use App\Models\Client;

/**
 * Class DefaultSettings
 * @package App\DataMapper
 */
class DefaultSettings extends BaseSettings
{

	/**
	 * @var int
     */
	public static $per_page = 25;

	/**
	 * @return \stdClass
     */
	public static function userSettings() : \stdClass
	{
		return (object)[
	        class_basename(Client::class) => self::clientSettings(),
	    ];
	}

	/**
	 * @return \stdClass
     */
	private static function clientSettings() : \stdClass
	{
		
		return (object)[
			'datatable' => (object) [
				'per_page' => self::$per_page,
				'column_visibility' => (object)[
	    			'name' => true,
	    			'contact' => true,
	    			'email' => true,
	    			'client_created_at' => true,
	    			'last_login' => true,
	    			'balance' => true,
	    			'custom_value1' => false,
	    			'custom_value2' => true,
	    			'custom_value3' => false,
	    			'custom_value4' => false,
				]
			]
		];

	}

}