<?php

namespace App\Ninja\PaymentDrivers;

use Request;

class PaymentExpressPxPayPaymentDriver extends BasePaymentDriver
{
    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails();

        $data['transactionId'] = substr($data['transactionId'] . '-' . strrev($this->invoice()->updated_at->timestamp), 0, 15);

        return $data;
    }
}
