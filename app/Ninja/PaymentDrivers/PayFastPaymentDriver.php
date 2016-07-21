<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class PayFastPaymentDriver
 */
class PayFastPaymentDriver extends BasePaymentDriver
{
    /**
     * @var string
     */
    protected $transactionReferenceParam = 'm_payment_id';

    public function completeOffsitePurchase(array $input)
    {
        if ($accountGateway->isGateway(GATEWAY_PAYFAST) && Request::has('pt')) {
            $token = Request::query('pt');
        }
    }
}
