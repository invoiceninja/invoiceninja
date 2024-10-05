<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Utils\Traits\MakesDates;
use Tests\TestCase;

/**
 * 
 *   App\Utils\Traits\MakesDates
 */
class MakesDatesTest extends TestCase
{
    use MakesDates;

    /*
     * tests may break with daylight savings changes ( as PHP handle DST under the hood )
     * these tests are to confirm the timezone conversions
     * work as expected at this point in time.
     */

    public function testConvertClientDateToUTCDateTimeTickOverSameDay()
    {
        $date_src = '2007-04-19 23:59';
        $client_timezone = 'Europe/Amsterdam'; // +1 UTC
        $date_time = new \DateTime($date_src, new \DateTimeZone($client_timezone));

        $utc_date = $this->createUtcDate($date_time, $client_timezone);

        $this->assertEquals('2007-04-19 21:59', $date_time->format('Y-m-d H:i'));
    }

    public function testConvertClientDateToUTCDateTimeSameDay()
    {
        $date_src = '2007-04-19 21:59';
        $client_timezone = 'Europe/Amsterdam'; // +1 UTC
        $date_time = new \DateTime($date_src, new \DateTimeZone($client_timezone));

        $utc_date = $this->createUtcDate($date_time, $client_timezone);

        $this->assertEquals($utc_date->format('Y-m-d'), '2007-04-19');
    }

    public function testConvertClientDateToUTCDateTimeTickOverNextDay()
    {
        $date_src = '2007-04-19 23:59';
        $client_timezone = 'Atlantic/Cape_Verde'; // -1 UTC
        $date_time = new \DateTime($date_src, new \DateTimeZone($client_timezone));

        $utc_date = $this->createUtcDate($date_time, $client_timezone);

        $this->assertEquals('2007-04-20 00:59', $date_time->format('Y-m-d H:i'));
    }

    public function testConvertClientDateToUTCDateTimeSameDayDiffTimeZone()
    {
        $date_src = '2007-04-19 22:59';
        $client_timezone = 'Atlantic/Cape_Verde'; // -1 UTC
        $date_time = new \DateTime($date_src, new \DateTimeZone($client_timezone));

        $utc_date = $this->createUtcDate($date_time, $client_timezone);

        $this->assertEquals('2007-04-19 23:59', $date_time->format('Y-m-d H:i'));
    }

    public function testCreateClientDate()
    {
        $client_date_src = '2007-04-19 22:59';
        $client_timezone = 'Atlantic/Cape_Verde'; // -1 UTC
        $date_time = new \DateTime($client_date_src, new \DateTimeZone($client_timezone));

        $utc_date = $this->createUtcDate($date_time, $client_timezone);
        $client_date = $this->createClientDate($utc_date, $client_timezone);

        $this->assertEquals('2007-04-19 22:59', $client_date->format('Y-m-d H:i'));
    }

    public function testCreateClientDateWithFormat()
    {
        $client_date_src = '2007-04-19';
        $client_timezone = 'Atlantic/Cape_Verde'; // -1 UTC
        $date_time = new \DateTime($client_date_src, new \DateTimeZone($client_timezone));

        $utc_date = $this->createUtcDate($date_time, $client_timezone);
        $client_date = $this->createClientDate($utc_date, $client_timezone);

        $this->assertEquals('2007-04-19', $client_date->format('Y-m-d'));
    }
}
