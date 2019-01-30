<?php

namespace App\Ninja\PaymentDrivers;

class EwayRapidSharedPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'AccessCode';
}
