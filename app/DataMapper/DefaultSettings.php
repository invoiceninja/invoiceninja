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
		];

	}

}