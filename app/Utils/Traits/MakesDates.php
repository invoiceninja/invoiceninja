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

/**
 * Class MakesDates
 * @package App\Utils\Traits
 */
trait MakesDates
{

	public function createClientDate($utc_date , $timezone)
	{

		return $utc_date->setTimezone(new \DateTimeZone($timezone));

	}


	public function createUtcDate($client_date)
	{

		return $client_date->setTimezone(new \DateTimeZone('GMT'));

	}

}