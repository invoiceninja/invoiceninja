<?php

namespace App\Models;

/**
 * Class DateFormat.
 */
class DateFormat extends StaticModel
{
    protected $fillable = ['translated_format'];

    public static $days_of_the_week = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    public static $months_of_the_years = [
        0 => 'january',
        1 => 'february',
        2 => 'march',
        3 => 'april',
        4 => 'may',
        5 => 'june',
        6 => 'july',
        7 => 'august',
        8 => 'september',
        9 => 'october',
        10 => 'november',
        11 => 'december',
    ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return bool|string
     */
    public function __toString()
    {
        $date = mktime(0, 0, 0, 12, 31, date('Y'));

        return date($this->format, $date);
    }
}
