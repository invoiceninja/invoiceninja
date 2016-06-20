<?php namespace App\Ninja\PaymentDrivers;

class BitPayPaymentDriver extends BasePaymentDriver
{
    protected function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_BITCOIN
        ];
    }

}
