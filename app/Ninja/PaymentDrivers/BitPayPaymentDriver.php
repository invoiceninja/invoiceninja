<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class BitPayPaymentDriver
 */
class BitPayPaymentDriver extends BasePaymentDriver
{
    /**
     * @return array
     */
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_BITCOIN
        ];
    }
}
