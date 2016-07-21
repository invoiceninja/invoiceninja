<?php namespace App\Ninja\PaymentDrivers;

class PayFastPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'm_payment_id';

    public function completeOffsitePurchase($input)
    {
        if ($accountGateway->isGateway(GATEWAY_PAYFAST) && Request::has('pt')) {
            $token = Request::query('pt');
        }
    }
}
