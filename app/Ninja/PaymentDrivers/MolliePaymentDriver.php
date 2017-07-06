<?php

namespace App\Ninja\PaymentDrivers;

use Exception;
use App\Models\Invitation;

class MolliePaymentDriver extends BasePaymentDriver
{
    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        // Enable webhooks
        $data['notifyUrl'] = url('/payment_hook/'. $this->account()->account_key . '/' . GATEWAY_MOLLIE);

        return $data;
    }

    public function completeOffsitePurchase($input)
    {
        $details = $this->paymentDetails();
        $details['transactionReference'] = $this->invitation->transaction_reference;

        $response = $this->gateway()->fetchTransaction($details)->send();

        if ($response->isCancelled() || ! $response->isSuccessful()) {
            return false;
        }

        return $this->createPayment($response->getTransactionReference());
    }

    public function handleWebHook($input)
    {
        $ref = array_get($input, 'id');
        $invitation = Invitation::whereAccountId($this->accountGateway->account_id)
                        ->whereTransactionReference($ref)
                        ->first();

        if ($invitation) {
          $this->invitation = $invitation;
        } else {
          return false;
        }

        $data = [
          'transactionReference' => $ref
        ];
        $response = $this->gateway()->fetchTransaction($data)->send();

        if ($response->isCancelled() || ! $response->isSuccessful()) {
            return false;
        }

        $this->createPayment($ref);

        return RESULT_SUCCESS;
    }

}
