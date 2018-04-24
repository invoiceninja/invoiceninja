<?php

namespace App\Ninja\PaymentDrivers;

class Custom3PaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_CUSTOM3,
        ];
    }
}
