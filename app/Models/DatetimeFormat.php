<?php

namespace App\Models;

/**
 * Class DatetimeFormat.
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
        $date = mktime(0, 0, 0, 12, 31, date('Y'));

        return date($this->format, $date);
    }
}
