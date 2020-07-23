<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Client;
use App\Models\Company;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Utils\Number;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyGateway extends BaseModel
{
    use SoftDeletes;
    
    protected $casts = [
        'fees_and_limits' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $fillable = [
        'gateway_key',
        'accepted_credit_cards',
        'require_cvv',
        'show_billing_address',
        'show_shipping_address',
        'update_details',
        'config',
        'fees_and_limits',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
    ];

    public static $credit_cards = [
            1 => ['card' => 'images/credit_cards/Test-Visa-Icon.png', 'text' => 'Visa'],
            2 => ['card' => 'images/credit_cards/Test-MasterCard-Icon.png', 'text' => 'Master Card'],
            4 => ['card' => 'images/credit_cards/Test-AmericanExpress-Icon.png', 'text' => 'American Express'],
            8 => ['card' => 'images/credit_cards/Test-Diners-Icon.png', 'text' => 'Diners'],
            16 => ['card' => 'images/credit_cards/Test-Discover-Icon.png', 'text' => 'Discover'],
        ];

    // public function getFeesAndLimitsAttribute()
    // {
    //     return json_decode($this->attributes['fees_and_limits']);
    // }

    protected $touches = [];

    public function getEntityType()
    {
        return CompanyGateway::class;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function gateway()
    {
        return $this->belongsTo(Gateway::class, 'gateway_key', 'key');
    }

    public function getTypeAlias($gateway_type_id)
    {
        if ($gateway_type_id == 'token') {
            $gateway_type_id = 1;
        }
        
        return GatewayType::find($gateway_type_id)->alias;
    }

    /* This is the public entry point into the payment superclass */
    public function driver(Client $client)
    {
        $class = static::driver_class();

        return new $class($this, $client);
    }

    private function driver_class()
    {
        $class = 'App\\PaymentDrivers\\' . $this->gateway->provider . 'PaymentDriver';
        //$class = str_replace('\\', '', $class);
        $class = str_replace('_', '', $class);

        if (class_exists($class)) {
            return $class;
        } else {
            return 'App\\PaymentDrivers\\BasePaymentDriver';
        }
    }

    /**
     * @param $config
     */
    public function setConfig($config)
    {
        $this->config = encrypt(json_encode($config));
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        //return decrypt($this->config);
        return json_decode(decrypt($this->config));
    }

    public function getConfigTransformed()
    {
        return $this->config ? decrypt($this->config) : '';
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
     * @return bool
     */
    public function getAchEnabled()
    {
        return ! empty($this->getConfigField('enable_ach'));
    }

    /**
     * @return bool
     */
    public function getApplePayEnabled()
    {
        return ! empty($this->getConfigField('enable_apple_pay'));
    }

    /**
     * @return bool
     */
    public function getAlipayEnabled()
    {
        return ! empty($this->getConfigField('enable_alipay'));
    }

    /**
     * @return bool
     */
    public function getSofortEnabled()
    {
        return ! empty($this->getConfigField('enable_sofort'));
    }

    /**
     * @return bool
     */
    public function getSepaEnabled()
    {
        return ! empty($this->getConfigField('enable_sepa'));
    }

    /**
     * @return bool
     */
    public function getBitcoinEnabled()
    {
        return ! empty($this->getConfigField('enable_bitcoin'));
    }

    /**
     * @return bool
     */
    public function getPayPalEnabled()
    {
        return ! empty($this->getConfigField('enable_pay_pal'));
    }

    public function feesEnabled()
    {
        return floatval($this->fee_amount) || floatval($this->fee_percent);
    }

    /**
     * Get Publishable Key
     * Only works for STRIPE and PAYMILL
     * @return string The Publishable key
     */
    public function getPublishableKey() :string
    {
        return $this->getConfigField('publishableKey');
    }

    /**
     * Returns the formatted fee amount for the gateway
     *
     * @param  float $amount    The payment amount
     * @param  Client $client   The client object
     * @return string           The fee amount formatted in the client currency
     */
    public function calcGatewayFeeLabel($amount, Client $client) :string
    {
        $label = '';

        if (!$this->feesEnabled()) {
            return $label;
        }

        $fee = $this->calcGatewayFee($amount);

        if ($fee > 0) {
            $fee = Number::formatMoney(round($fee, 2), $client);
            $label = ' - ' . $fee . ' ' . ctrans('texts.fee');
        }

        return $label;
    }

    public function calcGatewayFee($amount)
    {
        $fee = 0;

        if ($this->fee_amount) {
            $fee += $this->fee_amount;
        }
        
        if ($this->fee_percent) {
            $fee += $amount * $this->fee_percent / 100;
        }
        
        $pre_tax_fee = $fee;

        if ($this->fee_tax_rate1) {
            $fee += $pre_tax_fee * $this->fee_tax_rate1 / 100;
        }
        
        if ($this->fee_tax_rate2) {
            $fee += $pre_tax_fee * $this->fee_tax_rate2 / 100;
        }
            
        return $fee;
    }

    public function resolveRouteBinding($value)
    {
        return $this
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }
}
