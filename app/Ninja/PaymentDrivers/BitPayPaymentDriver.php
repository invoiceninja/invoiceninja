<?php

namespace App\Ninja\PaymentDrivers;

class BitPayPaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_BITCOIN,
        ];
    }
}
