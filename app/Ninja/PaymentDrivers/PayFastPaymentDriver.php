<?php

namespace App\Ninja\PaymentDrivers;

use Request;

class PayFastPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'm_payment_id';

    public function completeOffsitePurchase($input)
    {
        parent::completeOffsitePurchase([
            'token' => Request::query('pt'),
        ]);
    }
}
