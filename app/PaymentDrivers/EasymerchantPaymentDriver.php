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

use App\Utils\Traits\MakesHash;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\Models\Payment;
use App\Models\Gateway;
use App\Models\ClientGatewayToken;
use App\Models\PaymentHash;
use App\Jobs\Util\SystemLogger;
use App\PaymentDrivers\Easymerchant\CreditCard;
use App\PaymentDrivers\Easymerchant\ACH;

class EasymerchantPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    //maps GatewayType => Implementation class
    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_EASYMERCHANT; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    // public function init(): self
    // {
    //     $this->gateway = new Gateway([
    //         'environment' => $this->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production',
    //         'X-Api-Key' => $this->company_gateway->getConfigField('X-Api-Key'),
    //         'X-Api-Secret' => $this->company_gateway->getConfigField('X-Api-Secret'),
    //     ]);

    //     return $this;
    // }

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
    }
    
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        return 1;//$this->payment_method->yourRefundImplementationHere();
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return $this->payment_method->yourTokenBillingImplmentation();
    }
}
