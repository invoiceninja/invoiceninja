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

		return $utc_date->setTimezone(new \DateTimeZone($timezone));

	}


	public function createUtcDate($client_date)
	{

		return $client_date->setTimezone(new \DateTimeZone('GMT'));

	}

}