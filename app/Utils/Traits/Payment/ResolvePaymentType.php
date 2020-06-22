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

namespace App\Utils\Traits\Payment;

use App\Models\GatewayType;
use App\PaymentDrivers\Stripe\Alipay;
use App\PaymentDrivers\Stripe\CreditCard;

trait ResolvePaymentType
{
    static $types = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::ALIPAY => Alipay::class,
    ];

    public function resolvePaymentMethod($method_id)
    {
        if (isset(self::$types[$method_id])) {
            return $payment_method = self::$types[$method_id];
        }

        return false;
    }
}
