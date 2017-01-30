<?php

namespace App\Ninja\PaymentDrivers;

class CheckoutComPaymentDriver extends BasePaymentDriver
{
    public function createTransactionToken()
    {
        $response = $this->gateway()->purchase([
            'amount' => $this->invoice()->getRequestedAmount(),
            'currency' => $this->client()->getCurrencyCode(),
        ])->send();

        if ($response->isRedirect()) {
            $token = $response->getTransactionReference();

            $this->invitation->transaction_reference = $token;
            $this->invitation->save();

            return $token;
        }

        return false;
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails();

        if ($ref = array_get($this->input, 'token')) {
            $data['transactionReference'] = $ref;
        }

        return $data;
    }
}
