<?php

namespace App\Models;

use Utils;

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

    public function areFeesEnabled()
    {
        return floatval($this->fee_amount) || floatval($this->fee_percent);
    }

    public function hasTaxes()
    {
        return floatval($this->fee_tax_rate1) || floatval($this->fee_tax_rate1);
    }

    public function feesToString()
    {
        $parts = [];

        if (floatval($this->fee_amount) != 0) {
            $parts[] = Utils::formatMoney($this->fee_amount);
        }

        if (floatval($this->fee_percent) != 0) {
            $parts[] = (floor($this->fee_percent * 1000) / 1000) . '%';
        }

        return join(' + ', $parts);
    }
}
