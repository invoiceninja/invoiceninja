<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use Eloquent;

class Subscription extends Eloquent
{
    public $timestamps = true;
    use SoftDeletes;
    protected $dates = ['deleted_at'];
}
