<?php

namespace App\Ninja\PaymentDrivers;

class TwoCheckoutPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'cart_order_id';

    // Calling completePurchase results in an 'invalid key' error
    public function completeOffsitePurchase($input)
    {
        return $this->createPayment($input['order_number']);
    }
}
