<?php

namespace App\Ninja\PaymentDrivers;

use Exception;

class MolliePaymentDriver extends BasePaymentDriver
{
    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        // Enable the webhooks
        //$data['notifyUrl'] = $data['returnUrl'];
        $data['notifyUrl'] = url('/payment_hook/'. $this->account->account_key . '/' . GATEWAY_MOLLIE);

        return $data;
    }

    public function completeOffsitePurchase($input)
    {
        $details = $this->paymentDetails();

        $details['transactionReference'] = $this->invitation->transaction_reference;

        $response = $this->gateway()->fetchTransaction($details)->send();

        \Log::info('completeOffsitePurchase');
        \Log::info($response);

        if ($response->isCancelled()) {
            return false;
        } elseif (! $response->isSuccessful()) {
            throw new Exception($response->getMessage());
        }

        return $this->createPayment($response->getTransactionReference());
    }

    public function handleWebHook($input)
    {
        //$paymentId = array_get($input, 'id');
        $response = $this->gateway()->fetchTransaction($input)->send();

        \Log::info('handleWebHook');
        \Log::info($response);
        return 'Processed successfully';
    }

}
