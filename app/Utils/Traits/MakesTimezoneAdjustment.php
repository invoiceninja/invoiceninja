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

		$utc_date->setTimezone(new \DateTimeZone($timezone));

		return $utc_date;

	}


	public function createUtcDate($client_date)
	{

		$client_date->setTimezone(new \DateTimeZone('GMT'));

		return $client_date;
	}

}