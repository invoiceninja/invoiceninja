<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class EwayRapidSharedPaymentDriver
 */
class EwayRapidSharedPaymentDriver extends BasePaymentDriver
{
    /**
     * @var string
     */
    protected $transactionReferenceParam = 'AccessCode';
}
