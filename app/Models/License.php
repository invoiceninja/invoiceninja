<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Eloquent
{
    public $timestamps = true;
    use SoftDeletes;
    protected $dates = ['deleted_at'];
}
