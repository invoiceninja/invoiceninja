<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Company;
use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Eloquent\Model;

class CompanyGateway extends BaseModel
{
    public static $credit_cards = [
            1 => ['card' => 'images/credit_cards/Test-Visa-Icon.png', 'text' => 'Visa'],
            2 => ['card' => 'images/credit_cards/Test-MasterCard-Icon.png', 'text' => 'Master Card'],
            4 => ['card' => 'images/credit_cards/Test-AmericanExpress-Icon.png', 'text' => 'American Express'],
            8 => ['card' => 'images/credit_cards/Test-Diners-Icon.png', 'text' => 'Diners'],
            16 => ['card' => 'images/credit_cards/Test-Discover-Icon.png', 'text' => 'Discover'],
        ];

    public function company()
    {
    	return $this->belongsTo(Company::class);
    }

    public function gateway()
    {
    	return $this->hasOne(Gateway::class);
    }

    public function type()
    {
    	return $this->hasOne(GatewayType::class);
    }

    /* This is the public entry point into the payment superclass */
    public function driver()
    {
        $class = static::driver_class();

        return new $class($this);
    }

    private function driver_class()
    {
        $class = 'App\\PaymentDrivers\\' . $this->gateway->provider . 'PaymentDriver';
        $class = str_replace('\\', '', $class);
        $class = str_replace('_', '', $class);

        if (class_exists($class)) {
            return $class;
        } else {
            return 'App\\PaymentDrivers\\BasePaymentDriver';
        }
    }

    public function getConfigAttribute()
    {
        return decrypt($this->config);
    }

    public function setConfigAttribute($value)
    {
        $this->attributes['config'] = encrypt(json_encode($value));
    }

    /**
     * @return bool
     */
    public function getAchEnabled()
    {
        return ! empty($this->config('enableAch'));
    }

    /**
     * @return bool
     */
    public function getApplePayEnabled()
    {
        return ! empty($this->config('enableApplePay'));
    }

    /**
     * @return bool
     */
    public function getAlipayEnabled()
    {
        return ! empty($this->config('enableAlipay'));
    }

    /**
     * @return bool
     */
    public function getSofortEnabled()
    {
        return ! empty($this->config('enableSofort'));
    }

    /**
     * @return bool
     */
    public function getSepaEnabled()
    {
        return ! empty($this->config('enableSepa'));
    }

    /**
     * @return bool
     */
    public function getBitcoinEnabled()
    {
        return ! empty($this->config('enableBitcoin'));
    }

    /**
     * @return bool
     */
    public function getPayPalEnabled()
    {
        return ! empty($this->config('enablePayPal'));
    }
}
