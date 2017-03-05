<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class AccountGatewayToken.
 */
class AccountGatewayToken extends Eloquent
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];
    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array
     */
    protected $casts = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payment_methods()
    {
        return $this->hasMany('App\Models\PaymentMethod');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account_gateway()
    {
        return $this->belongsTo('App\Models\AccountGateway');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function default_payment_method()
    {
        return $this->hasOne('App\Models\PaymentMethod', 'id', 'default_payment_method_id');
    }

    /**
     * @return mixed
     */
    public function autoBillLater()
    {
        if ($this->default_payment_method) {
            return $this->default_payment_method->requiresDelayedAutoBill();
        }

        return false;
    }

    /**
     * @param $query
     * @param $clientId
     * @param $accountGatewayId
     *
     * @return mixed
     */
    public function scopeClientAndGateway($query, $clientId, $accountGatewayId)
    {
        $query->where('client_id', '=', $clientId)
            ->where('account_gateway_id', '=', $accountGatewayId);

        return $query;
    }

    /**
     * @return mixed
     */
    public function gatewayName()
    {
        return $this->account_gateway->gateway->name;
    }

    /**
     * @return bool|string
     */
    public function gatewayLink()
    {
        $accountGateway = $this->account_gateway;

        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            return "https://dashboard.stripe.com/customers/{$this->token}";
        } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
            $merchantId = $accountGateway->getConfigField('merchantId');
            $testMode = $accountGateway->getConfigField('testMode');

            return $testMode ? "https://sandbox.braintreegateway.com/merchants/{$merchantId}/customers/{$this->token}" : "https://www.braintreegateway.com/merchants/{$merchantId}/customers/{$this->token}";
        } else {
            return false;
        }
    }
}
