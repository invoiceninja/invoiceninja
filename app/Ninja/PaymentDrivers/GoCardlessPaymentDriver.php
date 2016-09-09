<?php namespace App\Ninja\PaymentDrivers;

class GoCardlessPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'signature';

    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_BANK_TRANSFER
        ];
    }
}
