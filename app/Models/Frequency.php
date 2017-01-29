<?php namespace App\Models;

use Eloquent;
use Cache;
use Str;

/**
 * Class Frequency
 */
class Frequency extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    public static function selectOptions()
    {
        $data = [];

        foreach (Cache::get('frequencies') as $frequency) {
            $name = Str::snake(str_replace(' ', '_', $frequency->name));
            $data[$frequency->id] = trans('texts.freq_' . $name);
        }

        return $data;
    }
}
