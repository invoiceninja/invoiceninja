<?php

namespace App\Models;

use Eloquent;

/**
 * Class Country.
 */
class Country extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $visible = [
        'id',
        'name',
        'swap_postal_code',
        'swap_currency_symbol',
        'thousand_separator',
        'decimal_separator',
        'iso_3166_2',
        'iso_3166_3',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'swap_postal_code' => 'boolean',
        'swap_currency_symbol' => 'boolean',
    ];

    /**
     * @return mixed
     */
    public function getName()
    {
        return trans('texts.country_' . $this->name);
    }
}
