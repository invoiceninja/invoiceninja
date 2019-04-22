<?php

namespace App\Utils\Traits;

/**
 * Class MakesDates
 * @package App\Utils\Traits
 */
trait MakesDates
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