<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class GoCardlessPaymentDriver
 */
class GoCardlessPaymentDriver extends BasePaymentDriver
{
    /**
     * @var string
     */
    protected $transactionReferenceParam = 'signature';
}
