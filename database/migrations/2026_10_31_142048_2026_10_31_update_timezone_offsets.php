<?php

use App\Models\Timezone;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Zones whose seeded location and utc_offset were left behind by a
     * national change: Russia leaving UTC+4 in 2014, Belarus in 2011,
     * the Falklands in 2010, Mexico dropping DST in 2022, Greenland
     * in 2023 and Kazakhstan in 2024. Asia/Urumqi was labelled with
     * Beijing time rather than the +06:00 that zone keeps.
     *
     * Values are standard time, which is what the rest of the table states.
     */
    private array $offsets = [
        'America/Chihuahua' => ['(GMT-06:00) Chihuahua', -21600],
        'America/Godthab' => ['(GMT-02:00) Greenland', -7200],
        'Atlantic/Stanley' => ['(GMT-03:00) Stanley', -10800],
        'Europe/Minsk' => ['(GMT+03:00) Minsk', 10800],
        'Europe/Moscow' => ['(GMT+03:00) Moscow', 10800],
        'Europe/Volgograd' => ['(GMT+03:00) Volgograd', 10800],
        'Asia/Yekaterinburg' => ['(GMT+05:00) Ekaterinburg', 18000],
        'Asia/Almaty' => ['(GMT+05:00) Almaty', 18000],
        'Asia/Krasnoyarsk' => ['(GMT+07:00) Krasnoyarsk', 25200],
        'Asia/Urumqi' => ['(GMT+06:00) Urumqi', 21600],
        'Asia/Irkutsk' => ['(GMT+08:00) Irkutsk', 28800],
        'Asia/Yakutsk' => ['(GMT+09:00) Yakutsk', 32400],
        'Asia/Vladivostok' => ['(GMT+10:00) Vladivostok', 36000],
        'Asia/Magadan' => ['(GMT+11:00) Magadan', 39600],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->offsets as $name => [$location, $utcOffset]) {
            Timezone::where('name', $name)->update([
                'location' => $location,
                'utc_offset' => $utcOffset,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
