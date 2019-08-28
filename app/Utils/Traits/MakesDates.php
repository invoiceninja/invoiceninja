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

use Illuminate\Support\Facades\Log;

/**
 * Class MakesDates
 * @package App\Utils\Traits
 */
trait MakesDates
{

	/**
	 * Converts from UTC to client timezone
	 * @param  datetime 	object 		$utc_date 
	 * @param  string 		$timezone 	ie Australia/Sydney
	 * @return Carbon           		Carbon object
	 */
	public function createClientDate($utc_date , $timezone)
	{
Log::error($utc_date. ' '. $timezone);

		if(is_string($utc_date))
			$utc_date = $this->convertToDateObject($utc_date);

		return $utc_date->setTimezone(new \DateTimeZone($timezone));

	}

	/**
	 * Converts from client timezone to UTC
	 * @param  datetime 	object 		$utc_date 
	 * @param  string 		$timezone 	ie Australia/Sydney
	 * @return Carbon           		Carbon object
	 */
	public function createUtcDate($client_date)
	{

		if(is_string($client_date))
			$client_date = $this->convertToDateObject($client_date);

		return $client_date->setTimezone(new \DateTimeZone('GMT'));

	}

	private function convertToDateObject($date)
	{

    	return new \DateTime($date); 

	}

}