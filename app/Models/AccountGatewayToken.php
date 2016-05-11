<?php namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountGatewayToken extends Eloquent
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $casts = [
        'uses_local_payment_methods' => 'boolean',
    ];

    public function payment_methods()
    {
        return $this->hasMany('App\Models\PaymentMethod');
    }

    public function default_payment_method()
    {
        return $this->hasOne('App\Models\PaymentMethod', 'id', 'default_payment_method_id');
    }
}