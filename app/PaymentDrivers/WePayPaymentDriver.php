<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\WePay\CreditCard;
use App\PaymentDrivers\WePay\Setup;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use WePay;

class WePayPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $wepay; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class, //maps GatewayType => Implementation class
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_WEPAY; 

    public function init()
    {
        
        if (WePay::getEnvironment() == 'none') {
            
            if(config('ninja.wepay.environment') == 'staging')
                WePay::useStaging(config('ninja.wepay.client_id'), config('ninja.wepay.client_secret'));
            else
                WePay::useProduction(config('ninja.wepay.client_id'), config('ninja.wepay.client_secret'));

        }

        if ($this->company_gateway) 
            $this->wepay = new WePay($this->company_gateway->getConfig()->accessToken);

        $this->wepay = new WePay(null);
        
        return $this;
        
    }

    public function setup(array $data)
    {
        return (new Setup($this))->boot($data);
    }

    public function processSetup(Request $request)
    {
        return (new Setup($this))->processSignup($request);
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data); //this is your custom implementation from here
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);  //this is your custom implementation from here
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        return $this->payment_method->yourRefundImplementationHere(); //this is your custom implementation from here
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return $this->payment_method->yourTokenBillingImplmentation(); //this is your custom implementation from here
    }
}
