<?php namespace App\Models;

use Auth;

/**
 * Class AccountGatewaySettings
 */
class AccountGatewaySettings extends EntityModel
{
    /**
     * @var array
     */
    protected $dates = ['updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function gatewayType()
    {
        return $this->belongsTo('App\Models\GatewayType');
    }

    public function setCreatedAtAttribute($value)
    {
        // to Disable created_at
    }
}
