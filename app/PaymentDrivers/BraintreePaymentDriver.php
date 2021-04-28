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


use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\Braintree\CreditCard;

class BraintreePaymentDriver extends BaseDriver
{
    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public $gateway;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::PAYPAL,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_BRAINTREE;

    public function init()
    {

    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
            GatewayType::PAYPAL,
        ];
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }
}
