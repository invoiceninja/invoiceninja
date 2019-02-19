<?php

namespace App\Ninja\PaymentDrivers;

use Request;

class PaymentExpressPxPostPaymentDriver extends BasePaymentDriver
{
    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails();

        $data['transactionId'] = $data['transactionId'] . '-' . $this->invoice()->updated_at->timestamp;

        return $data;
    }
}
