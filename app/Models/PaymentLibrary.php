<?php namespace App\Models;

use Eloquent;

class PaymentLibrary extends Eloquent
{
    protected $table = 'payment_libraries';
    public $timestamps = true;

    public function gateways()
    {
        return $this->hasMany('App\Models\Gateway', 'payment_library_id');
    }
}
