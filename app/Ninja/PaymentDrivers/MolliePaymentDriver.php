<?php namespace App\Ninja\PaymentDrivers;

class MolliePaymentDriver extends BasePaymentDriver
{
    public function completeOffsitePurchase($input)
    {
        $details = $this->paymentDetails();

        $details['transactionReference'] = $this->invitation->transaction_reference;

        $response = $this->gateway()->fetchTransaction($details)->send();

        return $this->createPayment($response->getTransactionReference());
    }

}
