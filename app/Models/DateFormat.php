<?php

namespace App\Models;

/**
 * Class DateFormat.
 *
 * @property int $id
 * @property string $format
 * @property string $format_moment
 * @property string $format_dart
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|DateFormat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DateFormat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DateFormat query()
 * @method static \Illuminate\Database\Eloquent\Builder|DateFormat whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DateFormat whereFormatDart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DateFormat whereFormatMoment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DateFormat whereId($value)
 * @mixin \Eloquent
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
        $date = mktime(0, 0, 0, 12, 31, date('Y')); //@phpstan-ignore-line

        return date($this->format, $date);
    }
}
