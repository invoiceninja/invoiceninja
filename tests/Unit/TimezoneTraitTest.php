<?php

namespace Tests\Unit;

use App\Utils\Traits\MakesTimezoneAdjustment;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Traits\MakesTimezoneAdjustment
 */
class TimezoneTraitTest extends TestCase
{

	use MakesTimezoneAdjustment;


	public function testConvertClientDateToUTCDateTimeTickOverNextDay()
	{

	    $date_src = '2007-04-19 22:59'; 
	    $client_timezone = 'Europe/Amsterdam'; // +1 UTC
    	$date_time = new \DateTime($date_src, new \DateTimeZone($client_timezone)); 

    	$utc_date = $this->createClientDate($date_time, $client_timezone);

    	$this->assertEquals($utc_date->format('Y-m-d'), '2007-04-19');
	}

	public function testConvertClientDateToUTCDateTimeSameDay()
	{

	    $date_src = '2007-04-19 21:59'; 
	    $client_timezone = 'Europe/Amsterdam'; // +1 UTC
    	$date_time = new \DateTime($date_src, new \DateTimeZone($client_timezone)); 

    	$utc_date = $this->createUtcDate($date_time, $client_timezone);

    	$this->assertEquals($utc_date->format('Y-m-d'), '2007-04-19');
	}

}