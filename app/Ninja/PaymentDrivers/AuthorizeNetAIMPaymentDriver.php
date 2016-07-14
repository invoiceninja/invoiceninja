<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class AuthorizeNetAIMPaymentDriver
 */
class AuthorizeNetAIMPaymentDriver extends BasePaymentDriver
{
    /**
     * @var string
     */
    protected $transactionReferenceParam = 'refId';
}
