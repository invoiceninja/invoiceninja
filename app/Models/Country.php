<?php namespace App\Models;

use Eloquent;

class Country extends Eloquent
{
    public $timestamps = false;

    protected $visible = [
        'id',
        'name',
        'swap_postal_code',
        'swap_currency_symbol',
        'thousand_separator',
        'decimal_separator'
    ];

    protected $casts = [
        'swap_postal_code' => 'boolean',
        'swap_currency_symbol' => 'boolean',
    ];

    public function getName() 
    {
        return $this->name;
    }
}
