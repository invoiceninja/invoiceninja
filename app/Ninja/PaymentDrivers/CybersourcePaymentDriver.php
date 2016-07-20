<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class CybersourcePaymentDriver
 */
class CybersourcePaymentDriver extends BasePaymentDriver
{
    /**
     * @var string
     */
    protected $transactionReferenceParam = 'transaction_uuid';

    /**
     * @param array $input
     * 
     * @return \App\Models\Payment|mixed
     * @throws Exception
     */
    public function completeOffsitePurchase(array $input)
    {
        if ($input['decision'] == 'ACCEPT') {
            return $this->createPayment($input['bill_trans_ref_no']);
        } else {
            throw new Exception($input['message'] . ': ' . $input['invalid_fields']);
        }
    }
}
