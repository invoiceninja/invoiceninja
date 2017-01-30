<?php

namespace App\Ninja\PaymentDrivers;

class GoCardlessPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'signature';
}
