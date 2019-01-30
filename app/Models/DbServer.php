<?php

namespace App\Models;

use Eloquent;

/**
 * Class ExpenseCategory.
 */
class DbServer extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $fillable = [
        'name',
    ];

}
