<?php

namespace App\Models;

use Eloquent;

/**
 * Class PaymentType.
 */
class PaymentType extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function gatewayType()
    {
        return $this->belongsTo('App\Models\GatewayType');
    }
}
