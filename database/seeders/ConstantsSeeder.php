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

namespace Database\Seeders;

use App\Models\PaymentLibrary;
use App\Models\Size;
use App\Models\Timezone;
use Illuminate\Database\Seeder;

class ConstantsSeeder extends Seeder
{
    public function run()
    {
        Size::create(['id' => 1, 'name' => '1 - 3']);
        Size::create(['id' => 2, 'name' => '4 - 10']);
        Size::create(['id' => 3, 'name' => '11 - 50']);
        Size::create(['id' => 4, 'name' => '51 - 100']);
        Size::create(['id' => 5, 'name' => '101 - 500']);
        Size::create(['id' => 6, 'name' => '500+']);

        PaymentLibrary::create(['name' => 'Omnipay']);

        /*
        d, dd: Numeric date, no leading zero and leading zero, respectively. Eg, 5, 05.
        D, DD: Abbreviated and full weekday names, respectively. Eg, Mon, Monday.
        m, mm: Numeric month, no leading zero and leading zero, respectively. Eg, 7, 07.
        M, MM: Abbreviated and full month names, respectively. Eg, Jan, January
        yy, yyyy: 2- and 4-digit years, respectively. Eg, 12, 2012.)
        */

        $timezones[] = ['name'=>'Pacific/Midway', 'location', 'location' => '(GMT-11:00) Midway Island', 'utc_offset' => -39600];
        $timezones[] = ['name'=>'US/Samoa', 'location'  => '(GMT-11:00) Samoa', 'utc_offset' => -39600];
        $timezones[] = ['name'=>'US/Hawaii', 'location'  => '(GMT-10:00) Hawaii', 'utc_offset' => -36000];
        $timezones[] = ['name'=>'US/Alaska', 'location'  => '(GMT-09:00) Alaska', 'utc_offset' => -32400];
        $timezones[] = ['name'=>'US/Pacific', 'location'  => '(GMT-08:00) Pacific Time (US & Canada)', 'utc_offset' => -28800];
        $timezones[] = ['name'=>'America/Tijuana', 'location'  => '(GMT-08:00) Tijuana', 'utc_offset' => -28800];
        $timezones[] = ['name'=>'US/Arizona', 'location'  => '(GMT-07:00) Arizona', 'utc_offset' => -25200];
        $timezones[] = ['name'=>'US/Mountain', 'location'  => '(GMT-07:00) Mountain Time (US & Canada)', 'utc_offset' => -25200];
        $timezones[] = ['name'=>'America/Chihuahua', 'location' => '(GMT-07:00) Chihuahua', 'utc_offset' => -25200];
        $timezones[] = ['name'=>'America/Mazatlan', 'location' => '(GMT-07:00) Mazatlan', 'utc_offset' => -25200];
        $timezones[] = ['name'=>'America/Mexico_City', 'location' => '(GMT-06:00) Mexico City', 'utc_offset' => -21600];
        $timezones[] = ['name'=>'America/Monterrey', 'location' => '(GMT-06:00) Monterrey', 'utc_offset' => -21600];
        $timezones[] = ['name'=>'Canada/Saskatchewan', 'location' => '(GMT-06:00) Saskatchewan', 'utc_offset' => -21600];
        $timezones[] = ['name'=>'US/Central', 'location' => '(GMT-06:00) Central Time (US & Canada)', 'utc_offset' => -21600];
        $timezones[] = ['name'=>'US/Eastern', 'location' => '(GMT-05:00) Eastern Time (US & Canada)', 'utc_offset' => -18000];
        $timezones[] = ['name'=>'US/East-Indiana', 'location' => '(GMT-05:00) Indiana (East)', 'utc_offset' => -18000];
        $timezones[] = ['name'=>'America/Bogota', 'location' => '(GMT-05:00) Bogota', 'utc_offset' => -18000];
        $timezones[] = ['name'=>'America/Lima', 'location' => '(GMT-05:00) Lima', 'utc_offset' => -18000];
        $timezones[] = ['name'=>'America/Caracas', 'location' => '(GMT-04:00) Caracas', 'utc_offset' => -14400];
        $timezones[] = ['name'=>'Canada/Atlantic', 'location' => '(GMT-04:00) Atlantic Time (Canada)', 'utc_offset' => -14400];
        $timezones[] = ['name'=>'America/La_Paz', 'location' => '(GMT-04:00) La Paz', 'utc_offset' => -14400];
        $timezones[] = ['name'=>'America/Santiago', 'location' => '(GMT-04:00) Santiago', 'utc_offset' => -14400];
        $timezones[] = ['name'=>'Canada/Newfoundland', 'location' => '(GMT-03:30) Newfoundland', 'utc_offset' => -12600];
        $timezones[] = ['name'=>'America/Buenos_Aires', 'location' => '(GMT-03:00) Buenos Aires', 'utc_offset' => -10800];
        $timezones[] = ['name'=>'America/Godthab', 'location' => '(GMT-03:00) Greenland', 'utc_offset' => -10800];
        $timezones[] = ['name'=>'Atlantic/Stanley', 'location' => '(GMT-02:00) Stanley', 'utc_offset' => -7200];
        $timezones[] = ['name'=>'Atlantic/Azores', 'location' => '(GMT-01:00) Azores', 'utc_offset' => -3600];
        $timezones[] = ['name'=>'Atlantic/Cape_Verde', 'location' => '(GMT-01:00) Cape Verde Is.', 'utc_offset' => -3600];
        $timezones[] = ['name'=>'Africa/Casablanca', 'location' => '(GMT) Casablanca', 'utc_offset' => 0];
        $timezones[] = ['name'=>'Europe/Dublin', 'location' => '(GMT) Dublin', 'utc_offset' => 0];
        $timezones[] = ['name'=>'Europe/Lisbon', 'location' => '(GMT) Lisbon', 'utc_offset' => 0];
        $timezones[] = ['name'=>'Europe/London', 'location' => '(GMT) London', 'utc_offset' => 0];
        $timezones[] = ['name'=>'Africa/Monrovia', 'location' => '(GMT) Monrovia', 'utc_offset' => 0];
        $timezones[] = ['name'=>'Europe/Amsterdam', 'location' => '(GMT+01:00) Amsterdam', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Belgrade', 'location' => '(GMT+01:00) Belgrade', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Berlin', 'location' => '(GMT+01:00) Berlin', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Bratislava', 'location' => '(GMT+01:00) Bratislava', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Brussels', 'location' => '(GMT+01:00) Brussels', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Budapest', 'location' => '(GMT+01:00) Budapest', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Copenhagen', 'location' => '(GMT+01:00) Copenhagen', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Ljubljana', 'location' => '(GMT+01:00) Ljubljana', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Madrid', 'location' => '(GMT+01:00) Madrid', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Paris', 'location' => '(GMT+01:00) Paris', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Prague', 'location' => '(GMT+01:00) Prague', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Rome', 'location' => '(GMT+01:00) Rome', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Sarajevo', 'location' => '(GMT+01:00) Sarajevo', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Skopje', 'location' => '(GMT+01:00) Skopje', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Stockholm', 'location' => '(GMT+01:00) Stockholm', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Vienna', 'location' => '(GMT+01:00) Vienna', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Warsaw', 'location' => '(GMT+01:00) Warsaw', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Zagreb', 'location' => '(GMT+01:00) Zagreb', 'utc_offset' => 3600];
        $timezones[] = ['name'=>'Europe/Athens', 'location' => '(GMT+02:00) Athens', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Bucharest', 'location' => '(GMT+02:00) Bucharest', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Africa/Cairo', 'location' => '(GMT+02:00) Cairo', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Africa/Harare', 'location' => '(GMT+02:00) Harare', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Helsinki', 'location' => '(GMT+02:00) Helsinki', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Asia/Jerusalem', 'location' => '(GMT+02:00) Jerusalem', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Kiev', 'location' => '(GMT+02:00) Kyiv', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Minsk', 'location' => '(GMT+02:00) Minsk', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Riga', 'location' => '(GMT+02:00) Riga', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Sofia', 'location' => '(GMT+02:00) Sofia', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Tallinn', 'location' => '(GMT+02:00) Tallinn', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Vilnius', 'location' => '(GMT+02:00) Vilnius', 'utc_offset' => 7200];
        $timezones[] = ['name'=>'Europe/Istanbul', 'location' => '(GMT+03:00) Istanbul', 'utc_offset' => 10800];
        $timezones[] = ['name'=>'Asia/Baghdad', 'location' => '(GMT+03:00) Baghdad', 'utc_offset' => 10800];
        $timezones[] = ['name'=>'Asia/Kuwait', 'location' => '(GMT+03:00) Kuwait', 'utc_offset' => 10800];
        $timezones[] = ['name'=>'Africa/Nairobi', 'location' => '(GMT+03:00) Nairobi', 'utc_offset' => 10800];
        $timezones[] = ['name'=>'Asia/Riyadh', 'location' => '(GMT+03:00) Riyadh', 'utc_offset' => 10800];
        $timezones[] = ['name'=>'Asia/Tehran', 'location' => '(GMT+03:30) Tehran', 'utc_offset' => 12600];
        $timezones[] = ['name'=>'Europe/Moscow', 'location' => '(GMT+04:00) Moscow', 'utc_offset' => 14400];
        $timezones[] = ['name'=>'Asia/Baku', 'location' => '(GMT+04:00) Baku', 'utc_offset' => 14400];
        $timezones[] = ['name'=>'Europe/Volgograd', 'location' => '(GMT+04:00) Volgograd', 'utc_offset' => 14400];
        $timezones[] = ['name'=>'Asia/Muscat', 'location' => '(GMT+04:00) Muscat', 'utc_offset' => 14400];
        $timezones[] = ['name'=>'Asia/Tbilisi', 'location' => '(GMT+04:00) Tbilisi', 'utc_offset' => 14400];
        $timezones[] = ['name'=>'Asia/Yerevan', 'location' => '(GMT+04:00) Yerevan', 'utc_offset' => 14400];
        $timezones[] = ['name'=>'Asia/Kabul', 'location' => '(GMT+04:30) Kabul', 'utc_offset' => 16200];
        $timezones[] = ['name'=>'Asia/Karachi', 'location' => '(GMT+05:00) Karachi', 'utc_offset' => 18000];
        $timezones[] = ['name'=>'Asia/Tashkent', 'location' => '(GMT+05:00) Tashkent', 'utc_offset' => 18000];
        $timezones[] = ['name'=>'Asia/Kolkata', 'location' => '(GMT+05:30) Kolkata', 'utc_offset' => 19800];
        $timezones[] = ['name'=>'Asia/Kathmandu', 'location' => '(GMT+05:45) Kathmandu', 'utc_offset' => 20700];
        $timezones[] = ['name'=>'Asia/Yekaterinburg', 'location' => '(GMT+06:00) Ekaterinburg', 'utc_offset' => 21600];
        $timezones[] = ['name'=>'Asia/Almaty', 'location' => '(GMT+06:00) Almaty', 'utc_offset' => 21600];
        $timezones[] = ['name'=>'Asia/Dhaka', 'location' => '(GMT+06:00) Dhaka', 'utc_offset' => 21600];
        $timezones[] = ['name'=>'Asia/Novosibirsk', 'location' => '(GMT+07:00) Novosibirsk', 'utc_offset' => 25200];
        $timezones[] = ['name'=>'Asia/Bangkok', 'location' => '(GMT+07:00) Bangkok', 'utc_offset' => 25200];
        $timezones[] = ['name'=>'Asia/Ho_Chi_Minh', 'location' => '(GMT+07.00) Ho Chi Minh', 'utc_offset' => 25200];
        $timezones[] = ['name'=>'Asia/Jakarta', 'location' => '(GMT+07:00) Jakarta', 'utc_offset' => 25200];
        $timezones[] = ['name'=>'Asia/Krasnoyarsk', 'location' => '(GMT+08:00) Krasnoyarsk', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Asia/Chongqing', 'location' => '(GMT+08:00) Chongqing', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Asia/Hong_Kong', 'location' => '(GMT+08:00) Hong Kong', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Asia/Kuala_Lumpur', 'location' => '(GMT+08:00) Kuala Lumpur', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Australia/Perth', 'location' => '(GMT+08:00) Perth', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Asia/Singapore', 'location' => '(GMT+08:00) Singapore', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Asia/Taipei', 'location' => '(GMT+08:00) Taipei', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Asia/Ulaanbaatar', 'location' => '(GMT+08:00) Ulaan Bataar', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Asia/Urumqi', 'location' => '(GMT+08:00) Urumqi', 'utc_offset' => 28800];
        $timezones[] = ['name'=>'Asia/Irkutsk', 'location' => '(GMT+09:00) Irkutsk', 'utc_offset' => 32400];
        $timezones[] = ['name'=>'Asia/Seoul', 'location' => '(GMT+09:00) Seoul', 'utc_offset' => 32400];
        $timezones[] = ['name'=>'Asia/Tokyo', 'location' => '(GMT+09:00) Tokyo', 'utc_offset' => 32400];
        $timezones[] = ['name'=>'Australia/Adelaide', 'location' => '(GMT+09:30) Adelaide', 'utc_offset' => 34200];
        $timezones[] = ['name'=>'Australia/Darwin', 'location' => '(GMT+09:30) Darwin', 'utc_offset' => 34200];
        $timezones[] = ['name'=>'Asia/Yakutsk', 'location' => '(GMT+10:00) Yakutsk', 'utc_offset' => 36000];
        $timezones[] = ['name'=>'Australia/Brisbane', 'location' => '(GMT+10:00) Brisbane', 'utc_offset' => 36000];
        $timezones[] = ['name'=>'Australia/Canberra', 'location' => '(GMT+10:00) Canberra', 'utc_offset' => 36000];
        $timezones[] = ['name'=>'Pacific/Guam', 'location' => '(GMT+10:00) Guam', 'utc_offset' => 36000];
        $timezones[] = ['name'=>'Australia/Hobart', 'location' => '(GMT+10:00) Hobart', 'utc_offset' => 36000];
        $timezones[] = ['name'=>'Australia/Melbourne', 'location' => '(GMT+10:00) Melbourne', 'utc_offset' => 36000];
        $timezones[] = ['name'=>'Pacific/Port_Moresby', 'location' => '(GMT+10:00) Port Moresby', 'utc_offset' => 36000];
        $timezones[] = ['name'=>'Australia/Sydney', 'location' => '(GMT+10:00) Sydney', 'utc_offset' => 36000];
        $timezones[] = ['name'=>'Asia/Vladivostok', 'location' => '(GMT+11:00) Vladivostok', 'utc_offset' => 39600];
        $timezones[] = ['name'=>'Asia/Magadan', 'location' => '(GMT+12:00) Magadan', 'utc_offset' => 43200];
        $timezones[] = ['name'=>'Pacific/Auckland', 'location' => '(GMT+12:00) Auckland', 'utc_offset' => 43200];
        $timezones[] = ['name'=>'Pacific/Fiji', 'location' => '(GMT+12:00) Fiji', 'utc_offset' => 43200];

        $x = 1;
        foreach ($timezones as $timezone) {
            Timezone::create([
                'id' => $x,
                'name' => $timezone['name'],
                'location' => $timezone['location'],
                'utc_offset' => $timezone['utc_offset'],
            ]);

            $x++;
        }
    }
}
