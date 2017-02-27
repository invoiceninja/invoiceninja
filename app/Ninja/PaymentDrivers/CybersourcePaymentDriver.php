<?php

namespace App\Ninja\PaymentDrivers;

class CybersourcePaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = 'transaction_uuid';

    public function completeOffsitePurchase($input)
    {
        if ($input['decision'] == 'ACCEPT') {
            return $this->createPayment($input['bill_trans_ref_no']);
        } else {
            throw new Exception($input['message'] . ': ' . $input['invalid_fields']);
        }
    }
}
