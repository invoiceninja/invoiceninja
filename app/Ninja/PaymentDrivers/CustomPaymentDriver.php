<?php

namespace App\Ninja\PaymentDrivers;

class CustomPaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_CUSTOM,
        ];
    }
}
