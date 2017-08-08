<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Subscription.
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
