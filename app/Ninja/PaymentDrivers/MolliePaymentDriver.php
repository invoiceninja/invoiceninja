<?php

namespace App\Ninja\PaymentDrivers;

use Exception;

class MolliePaymentDriver extends BasePaymentDriver
{
    public function completeOffsitePurchase($input)
    {
        $details = $this->paymentDetails();

        $details['transactionReference'] = $this->invitation->transaction_reference;

        $response = $this->gateway()->fetchTransaction($details)->send();

        if ($response->isCancelled()) {
            return false;
        } elseif (! $response->isSuccessful()) {
            throw new Exception($response->getMessage());
        }

        return $this->createPayment($response->getTransactionReference());
    }
}
