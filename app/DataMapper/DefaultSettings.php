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