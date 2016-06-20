<?php namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountGatewayToken extends Eloquent
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $casts = [];

    public function payment_methods()
    {
        return $this->hasMany('App\Models\PaymentMethod');
    }

    public function account_gateway()
    {
        return $this->belongsTo('App\Models\AccountGateway');
    }

    public function default_payment_method()
    {
        return $this->hasOne('App\Models\PaymentMethod', 'id', 'default_payment_method_id');
    }

    public function autoBillLater()
    {
        return $this->default_payment_method->requiresDelayedAutoBill();
    }

    public function scopeClientAndGateway($query, $clientId, $accountGatewayId)
    {
        $query->where('client_id', '=', $clientId)
            ->where('account_gateway_id', '=', $accountGatewayId);

        return $query;
    }

    public function gatewayName()
    {
        return $this->account_gateway->gateway->name;
    }

    public function gatewayLink()
    {
        $accountGateway = $this->account_gateway;

        if ($accountGateway->gateway_id == GATEWAY_STRIPE) {
            return "https://dashboard.stripe.com/customers/{$this->token}";
        } elseif ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
            $merchantId = $accountGateway->getConfig()->merchantId;
            $testMode = $accountGateway->getConfig()->testMode;
            return $testMode ? "https://sandbox.braintreegateway.com/merchants/{$merchantId}/customers/{$this->token}" : "https://www.braintreegateway.com/merchants/{$merchantId}/customers/{$this->token}";
        } else {
            return false;
        }
    }

}
