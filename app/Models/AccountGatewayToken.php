<?php namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountGatewayToken extends Eloquent
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;
}