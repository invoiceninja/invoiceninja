<?php namespace App\Models;

use Eloquent;

class DatetimeFormat extends Eloquent
{
    public $timestamps = false;

    public function __toString()
    {
        $date = mktime(0, 0, 0, 12, 31, date('Y'));

        return date($this->format, $date);
    }    
}
