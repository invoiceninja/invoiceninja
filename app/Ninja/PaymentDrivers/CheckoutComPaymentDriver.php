<?php

namespace App\Ninja\PaymentDrivers;

class CheckoutComPaymentDriver extends BasePaymentDriver
{
    public function createTransactionToken()
    {
        if( $this->invoice()->getCurrencyCode() == 'BHD')
        {
            $amount = $this->invoice()->getRequestedAmount()/10;
        }
        elseif($this->invoice()->getCurrencyCode() == 'KWD') 
        {
            $amount = $this->invoice()->getRequestedAmount()*10;

        }
        elseif($this->invoice()->getCurrencyCode() == 'OMR')
        {
            $amount = $this->invoice()->getRequestedAmount();
        }
        else
            $amount = $this->invoice()->getRequestedAmount();

        $response = $this->gateway()->purchase([
            'amount' => $amount,
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
