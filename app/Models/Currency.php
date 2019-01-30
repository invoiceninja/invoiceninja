<?php

namespace App\Models;

use Eloquent;
use Str;

/**
 * Class Currency.
 */
class Currency extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $casts = [
        'swap_currency_symbol' => 'boolean',
        'exchange_rate' => 'double',
    ];

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getTranslatedName()
    {
        return trans('texts.currency_' . Str::slug($this->name, '_'));
    }
}
