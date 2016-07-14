<?php

namespace App\Ninja\PaymentDrivers;

use App\Models\PaymentMethod;

/**
 * Class PayPalProPaymentDriver
 */
class PayPalProPaymentDriver extends BasePaymentDriver
{
    /**
     * @return array
     */
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_CREDIT_CARD
        ];
    }

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @return array
     */
    protected function paymentDetails(PaymentMethod $paymentMethod = false)
    {
        $data = parent::paymentDetails();

        $data['ButtonSource'] = 'InvoiceNinja_SP';

        return $data;
    }
}
