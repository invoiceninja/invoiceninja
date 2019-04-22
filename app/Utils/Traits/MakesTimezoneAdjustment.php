<?php

namespace App\Utils\Traits;

/**
 * Class MakesTimezoneAdjustment
 * @package App\Utils\Traits
 */
trait MakesTimezoneAdjustment
{

	public function createClientDate($utc_date , $timezone)
	{

		$userTimezone = new \DateTimeZone($timezone);
		$gmtTimezone = new \DateTimeZone('GMT');
		//$myDateTime = new \DateTime($utc_date, $gmtTimezone);
		$offset = $userTimezone->getOffset($utc_date);
		$myInterval = \DateInterval::createFromDateString((string)$offset . 'seconds');
		$utc_date->add($myInterval);

		return $utc_date;

	}


	public function createUtcDate($client_date, $timezone)
	{

		$userTimezone = new \DateTimeZone($timezone);
		$gmtTimezone = new \DateTimeZone('GMT');
		//$clientDateTime = new \DateTime($client_date, $userTimezone);
		$offset = $userTimezone->getOffset($client_date);
		$myInterval = \DateInterval::createFromDateString((string)$offset . 'seconds');
		$client_date->add($myInterval);

		return $client_date;
	}

}