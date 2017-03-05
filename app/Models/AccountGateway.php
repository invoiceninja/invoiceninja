<?php

namespace App\Models;

use Crypt;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class AccountGateway.
 */
class AccountGateway extends EntityModel
{
    use SoftDeletes;
    use PresentableTrait;

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\AccountGatewayPresenter';
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_ACCOUNT_GATEWAY;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function gateway()
    {
        return $this->belongsTo('App\Models\Gateway');
    }

    /**
     * @return array
     */
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

    /**
     * @param $provider
     *
     * @return string
     */
    public static function paymentDriverClass($provider)
    {
        $folder = 'App\\Ninja\\PaymentDrivers\\';
        $class = $folder . $provider . 'PaymentDriver';
        $class = str_replace('_', '', $class);

        if (class_exists($class)) {
            return $class;
        } else {
            return $folder . 'BasePaymentDriver';
        }
    }

    /**
     * @param bool  $invitation
     * @param mixed $gatewayTypeId
     *
     * @return mixed
     */
    public function paymentDriver($invitation = false, $gatewayTypeId = false)
    {
        $class = static::paymentDriverClass($this->gateway->provider);

        return new $class($this, $invitation, $gatewayTypeId);
    }

    /**
     * @param $gatewayId
     *
     * @return bool
     */
    public function isGateway($gatewayId)
    {
        return $this->gateway_id == $gatewayId;
    }

    /**
     * @param $config
     */
    public function setConfig($config)
    {
        $this->config = Crypt::encrypt(json_encode($config));
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return json_decode(Crypt::decrypt($this->config));
    }

    /**
     * @param $field
     *
     * @return mixed
     */
    public function getConfigField($field)
    {
        return object_get($this->getConfig(), $field, false);
    }

    /**
     * @return bool|mixed
     */
    public function getPublishableStripeKey()
    {
        if (! $this->isGateway(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->getConfigField('publishableKey');
    }

    /**
     * @return bool
     */
    public function getAchEnabled()
    {
        return ! empty($this->getConfigField('enableAch'));
    }

    /**
     * @return bool
     */
    public function getPayPalEnabled()
    {
        return ! empty($this->getConfigField('enablePayPal'));
    }

    /**
     * @return bool|mixed
     */
    public function getPlaidSecret()
    {
        if (! $this->isGateway(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->getConfigField('plaidSecret');
    }

    /**
     * @return bool|mixed
     */
    public function getPlaidClientId()
    {
        if (! $this->isGateway(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->getConfigField('plaidClientId');
    }

    /**
     * @return bool|mixed
     */
    public function getPlaidPublicKey()
    {
        if (! $this->isGateway(GATEWAY_STRIPE)) {
            return false;
        }

        return $this->getConfigField('plaidPublicKey');
    }

    /**
     * @return bool
     */
    public function getPlaidEnabled()
    {
        return ! empty($this->getPlaidClientId()) && $this->getAchEnabled();
    }

    /**
     * @return null|string
     */
    public function getPlaidEnvironment()
    {
        if (! $this->getPlaidClientId()) {
            return null;
        }

        $stripe_key = $this->getPublishableStripeKey();

        return substr(trim($stripe_key), 0, 8) == 'pk_test_' ? 'tartan' : 'production';
    }

    /**
     * @return string
     */
    public function getWebhookUrl()
    {
        $account = $this->account ? $this->account : Account::find($this->account_id);

        return \URL::to(env('WEBHOOK_PREFIX', '').'payment_hook/'.$account->account_key.'/'.$this->gateway_id.env('WEBHOOK_SUFFIX', ''));
    }
}
