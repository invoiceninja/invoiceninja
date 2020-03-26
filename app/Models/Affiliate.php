<?php

namespace App\Models;

use Eloquent;

/**
 * Class Affiliate.
 */
class Affiliate extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = true;
    /**
     * @var bool
     */
    protected $softDelete = true;
}
