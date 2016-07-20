<?php

namespace App\Ninja\PaymentDrivers;

/**
 * Class MolliePaymentDriver
 */
class MolliePaymentDriver extends BasePaymentDriver
{
    /**
     * @param $input
     * 
     * @return \App\Models\Payment|mixed
     */
    public function completeOffsitePurchase($input)
    {
        $details = $this->paymentDetails();

        $details['transactionReference'] = $this->invitation->transaction_reference;

        $response = $this->gateway()->fetchTransaction($details)->send();

        return $this->createPayment($response->getTransactionReference());
    }

}
