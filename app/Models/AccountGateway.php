<?php namespace App\Models;

use Crypt;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountGateway extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function getEntityType()
    {
        return ENTITY_ACCOUNT_GATEWAY;
    }

    public function gateway()
    {
        return $this->belongsTo('App\Models\Gateway');
    }

    public function getCreditcardTypes()
    {
        $flags = unserialize(CREDIT_CARDS);
        $arrayOfImages = [];

        foreach ($flags as $card => $name) {
            if (($this->accepted_credit_cards & $card) == $card) {
                $arrayOfImages[] = ['source' => asset($name['card']), 'alt' => $name['text']];
            }
        }

        return $arrayOfImages;
    }

    public function getPaymentType()
    {
        return Gateway::getPaymentType($this->gateway_id);
    }
    
    public function isPaymentType($type)
    {
        return $this->getPaymentType() == $type;
    }

    public function isGateway($gatewayId)
    {
        return $this->gateway_id == $gatewayId;
    }

    public function setConfig($config)
    {
        $this->config = Crypt::encrypt(json_encode($config));
    }

    public function getConfig()
    {
        return json_decode(Crypt::decrypt($this->config));
    }

    public function getConfigField($field)
    {
        return object_get($this->getConfig(), $field, false);
    }

    public function getPublishableStripeKey()
    {
        if ( ! $this->isGateway(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->getConfigField('publishableKey');
    }

    public function getAchEnabled()
    {
       return !empty($this->getConfigField('enableAch'));
    }

    public function getPlaidSecret()
    {
        if ( ! $this->isGateway(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->getConfigField('plaidSecret');
    }

    public function getPlaidClientId()
    {
        if ( ! $this->isGateway(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->getConfigField('plaidClientId');
    }

    public function getPlaidPublicKey()
    {
        if ( ! $this->isGateway(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->getConfigField('plaidPublicKey');
    }

    public function getPlaidEnabled()
    {
        return !empty($this->getPlaidClientId()) && $this->getAchEnabled();
    }

    public function getPlaidEnvironment()
    {
        if (!$this->getPlaidClientId()) {
            return null;
        }

        $stripe_key = $this->getPublishableStripeKey();

        return substr(trim($stripe_key), 0, 8) == 'sk_test_' ? 'tartan' : 'production';
    }
}

