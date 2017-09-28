<?php

namespace App\Ninja\PaymentDrivers;

class PagarmePaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'transactionReference';
}
