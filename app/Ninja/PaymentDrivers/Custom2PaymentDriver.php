<?php

namespace App\Ninja\PaymentDrivers;

class Custom2PaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_CUSTOM2,
        ];
    }
}
