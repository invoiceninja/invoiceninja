<?php namespace App\Models;

use Eloquent;

class Currency extends Eloquent
{
    public $timestamps = false;

    protected $casts = [
        'swap_currency_symbol' => 'boolean',
    ];

    public function getName()
    {
        return $this->name;
    }
}
