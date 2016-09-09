<?php namespace App\Models;

use Eloquent;

/**
 * Class GatewayType
 */
class GatewayType extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    public $incrementing = false;

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }
}
