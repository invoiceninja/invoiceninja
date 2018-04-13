<?php

namespace App\Ninja\PaymentDrivers;

class Custom1PaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_CUSTOM1,
        ];
    }
}
