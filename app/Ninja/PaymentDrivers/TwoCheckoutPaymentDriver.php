<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class TwoCheckoutPaymentDriver
 */
class TwoCheckoutPaymentDriver extends BasePaymentDriver
{
    /**
     * @var string
     */
    protected $transactionReferenceParam = 'cart_order_id';

    /**
     * Calling completePurchase results in an 'invalid key' error
     *
     * @param array $input
     * @return \App\Models\Payment|mixed
     */
    public function completeOffsitePurchase(array $input)
    {
        return $this->createPayment($input['order_number']);
    }
    
}
