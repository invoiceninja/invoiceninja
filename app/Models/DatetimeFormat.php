<?php

namespace App\Models;

/**
 * Class DatetimeFormat.
 *
 * @property int $id
 * @property string $format
 * @property string $format_moment
 * @property string $format_dart
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|DatetimeFormat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DatetimeFormat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DatetimeFormat query()
 * @method static \Illuminate\Database\Eloquent\Builder|DatetimeFormat whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DatetimeFormat whereFormatDart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DatetimeFormat whereFormatMoment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DatetimeFormat whereId($value)
 * @mixin \Eloquent
 */
class DatetimeFormat extends StaticModel
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return bool|string
     */
    public function __toString()
    {
        $date = mktime(0, 0, 0, 12, 31, date('Y'));  //@phpstan-ignore-line

        return date($this->format, $date);
    }
}
