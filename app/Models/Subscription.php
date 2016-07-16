<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Eloquent;

/**
 * Class Subscription
 */
class Subscription extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = true;
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];
}
