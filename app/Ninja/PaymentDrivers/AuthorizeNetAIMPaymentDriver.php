<?php

namespace App\Ninja\PaymentDrivers;

class AuthorizeNetAIMPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'refId';
}
