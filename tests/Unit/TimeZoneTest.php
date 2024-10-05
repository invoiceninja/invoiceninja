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

use Tests\TestCase;

/**
 * 
 */
class TimeZoneTest extends TestCase
{
    private array $timezones = [
        'Pacific/Midway' => 'Pacific/Midway',
        'US/Samoa' => 'Pacific/Pago_Pago',
        'US/Hawaii' => 'Pacific/Honolulu',
        'US/Alaska' => 'America/Anchorage',
        'US/Pacific' => 'America/Los_Angeles',
        'America/Tijuana' => 'America/Tijuana',
        'US/Arizona' => 'America/Phoenix',
        'US/Mountain' => 'America/Denver',
        'America/Chihuahua' => 'America/Chihuahua',
        'America/Mazatlan' => 'America/Mazatlan',
        'America/Mexico_City' => 'America/Mexico_City',
        'America/Monterrey' => 'America/Monterrey',
        'Canada/Saskatchewan' => 'America/Regina',
        'US/Central' => 'America/Chicago',
        'US/Eastern' => 'America/New_York',
        'US/East-Indiana' => 'America/Indiana/Indianapolis',
        'America/Bogota' => 'America/Bogota',
        'America/Lima' => 'America/Lima',
        'America/Caracas' => 'America/Caracas',
        'Canada/Atlantic' => 'America/Halifax',
        'America/La_Paz' => 'America/La_Paz',
        'America/Santiago' => 'America/Santiago',
        'Canada/Newfoundland' => 'America/St_Johns',
        'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
        'America/Godthab' => 'America/Godthab',
        'America/Sao_Paulo' => 'America/Sao_Paulo',
        'Atlantic/Stanley' => 'Atlantic/Stanley',
        'Atlantic/Azores' => 'Atlantic/Azores',
        'Atlantic/Cape_Verde' => 'Atlantic/Cape_Verde',
        'Africa/Casablanca' => 'Africa/Casablanca',
        'Europe/Dublin' => 'Europe/Dublin',
        'Europe/Lisbon' => 'Europe/Lisbon',
        'Europe/London' => 'Europe/London',
        'Africa/Monrovia' => 'Africa/Monrovia',
        'Europe/Amsterdam' => 'Europe/Amsterdam',
        'Europe/Belgrade' => 'Europe/Belgrade',
        'Europe/Berlin' => 'Europe/Berlin',
        'Europe/Bratislava' => 'Europe/Bratislava',
        'Europe/Brussels' => 'Europe/Brussels',
        'Europe/Budapest' => 'Europe/Budapest',
        'Europe/Copenhagen' => 'Europe/Copenhagen',
        'Europe/Ljubljana' => 'Europe/Ljubljana',
        'Europe/Madrid' => 'Europe/Madrid',
        'Europe/Paris' => 'Europe/Paris',
        'Europe/Prague' => 'Europe/Prague',
        'Europe/Rome' => 'Europe/Rome',
        'Europe/Sarajevo' => 'Europe/Sarajevo',
        'Europe/Skopje' => 'Europe/Skopje',
        'Europe/Stockholm' => 'Europe/Stockholm',
        'Europe/Vienna' => 'Europe/Vienna',
        'Europe/Warsaw' => 'Europe/Warsaw',
        'Europe/Zagreb' => 'Europe/Zagreb',
        'Europe/Athens' => 'Europe/Athens',
        'Europe/Bucharest' => 'Europe/Bucharest',
        'Africa/Cairo' => 'Africa/Cairo',
        'Africa/Harare' => 'Africa/Harare',
        'Europe/Helsinki' => 'Europe/Helsinki',
        'Asia/Jerusalem' => 'Asia/Jerusalem',
        'Europe/Kiev' => 'Europe/Kiev',
        'Europe/Minsk' => 'Europe/Minsk',
        'Europe/Riga' => 'Europe/Riga',
        'Europe/Sofia' => 'Europe/Sofia',
        'Europe/Tallinn' => 'Europe/Tallinn',
        'Europe/Vilnius' => 'Europe/Vilnius',
        'Europe/Istanbul' => 'Europe/Istanbul',
        'Asia/Baghdad' => 'Asia/Baghdad',
        'Asia/Kuwait' => 'Asia/Kuwait',
        'Africa/Nairobi' => 'Africa/Nairobi',
        'Asia/Riyadh' => 'Asia/Riyadh',
        'Asia/Tehran' => 'Asia/Tehran',
        'Europe/Moscow' => 'Europe/Moscow',
        'Asia/Baku' => 'Asia/Baku',
        'Europe/Volgograd' => 'Europe/Volgograd',
        'Asia/Muscat' => 'Asia/Muscat',
        'Asia/Tbilisi' => 'Asia/Tbilisi',
        'Asia/Yerevan' => 'Asia/Yerevan',
        'Asia/Kabul' => 'Asia/Kabul',
        'Asia/Karachi' => 'Asia/Karachi',
        'Asia/Tashkent' => 'Asia/Tashkent',
        'Asia/Kolkata' => 'Asia/Kolkata',
        'Asia/Kathmandu' => 'Asia/Kathmandu',
        'Asia/Yekaterinburg' => 'Asia/Yekaterinburg',
        'Asia/Almaty' => 'Asia/Almaty',
        'Asia/Dhaka' => 'Asia/Dhaka',
        'Asia/Novosibirsk' => 'Asia/Novosibirsk',
        'Asia/Bangkok' => 'Asia/Bangkok',
        'Asia/Ho_Chi_Minh' => 'Asia/Ho_Chi_Minh',
        'Asia/Jakarta' => 'Asia/Jakarta',
        'Asia/Krasnoyarsk' => 'Asia/Krasnoyarsk',
        'Asia/Chongqing' => 'Asia/Chongqing',
        'Asia/Hong_Kong' => 'Asia/Hong_Kong',
        'Asia/Kuala_Lumpur' => 'Asia/Kuala_Lumpur',
        'Australia/Perth' => 'Australia/Perth',
        'Asia/Singapore' => 'Asia/Singapore',
        'Asia/Taipei' => 'Asia/Taipei',
        'Asia/Ulaanbaatar' => 'Asia/Ulaanbaatar',
        'Asia/Urumqi' => 'Asia/Urumqi',
        'Asia/Irkutsk' => 'Asia/Irkutsk',
        'Asia/Seoul' => 'Asia/Seoul',
        'Asia/Tokyo' => 'Asia/Tokyo',
        'Australia/Adelaide' => 'Australia/Adelaide',
        'Australia/Darwin' => 'Australia/Darwin',
        'Asia/Yakutsk' => 'Asia/Yakutsk',
        'Australia/Brisbane' => 'Australia/Brisbane',
        'Australia/Canberra' => 'Australia/Sydney',
        'Pacific/Guam' => 'Pacific/Guam',
        'Australia/Hobart' => 'Australia/Hobart',
        'Australia/Melbourne' => 'Australia/Melbourne',
        'Pacific/Port_Moresby' => 'Pacific/Port_Moresby',
        'Australia/Sydney' => 'Australia/Sydney',
        'Asia/Vladivostok' => 'Asia/Vladivostok',
        'Asia/Magadan' => 'Asia/Magadan',
        'Pacific/Auckland' => 'Pacific/Auckland',
        'Pacific/Fiji' => 'Pacific/Fiji'
    ];

    protected function setUp(): void
    {
        parent::setUp();
    }


    public function testTimezoneCompatibility()
    {


        foreach($this->timezones as $timezone) {

            date_default_timezone_set('GMT');
            $date = new \DateTime("now", new \DateTimeZone($timezone));
            $this->assertIsNumeric($date->getOffset());

        }

    }
}
