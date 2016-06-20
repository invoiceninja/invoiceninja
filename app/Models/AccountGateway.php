<?php namespace App\Models;

use Crypt;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class AccountGateway extends EntityModel
{
    use SoftDeletes;
    use PresentableTrait;

    protected $presenter = 'App\Ninja\Presenters\AccountGatewayPresenter';
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

    public function paymentDriver($invitation = false, $gatewayType = false)
    {
        $folder = "App\\Ninja\\PaymentDrivers\\";
        $class = $folder . $this->gateway->provider . 'PaymentDriver';
        $class = str_replace('_', '', $class);

        if (class_exists($class)) {
            return new $class($this, $invitation, $gatewayType);
        } else {
            $baseClass = $folder . "BasePaymentDriver";
            return new $baseClass($this, $invitation, $gatewayType);
        }
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

    public function getPayPalEnabled()
    {
        return !empty($this->getConfigField('enablePayPal'));
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

        return substr(trim($stripe_key), 0, 8) == 'pk_test_' ? 'tartan' : 'production';
    }

    public function getWebhookUrl()
    {
        $account = $this->account ? $this->account : Account::find($this->account_id);
        return \URL::to(env('WEBHOOK_PREFIX','').'paymenthook/'.$account->account_key.'/'.$this->gateway_id.env('WEBHOOK_SUFFIX',''));
    }
}
