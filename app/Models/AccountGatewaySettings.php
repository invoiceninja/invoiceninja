<?php

namespace App\Models;

/**
 * Class AccountGatewaySettings.
 */
class AccountGatewaySettings extends EntityModel
{
    /**
     * @var array
     */
    protected $dates = ['updated_at'];

    /**
     * @var array
     */
    protected $fillable = [
        'fee_amount',
        'fee_percent',
        'fee_tax_name1',
        'fee_tax_rate1',
        'fee_tax_name2',
        'fee_tax_rate2',
    ];

    /**
     * @var bool
     */
    protected static $hasPublicId = false;

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
