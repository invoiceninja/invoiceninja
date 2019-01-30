<?php

namespace App\Ninja\PaymentDrivers;

use Exception;
use App\Models\Invitation;
use App\Models\Payment;

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
        // payment is created by the webhook
        return false;
    }

    public function handleWebHook($input)
    {
        $ref = array_get($input, 'id');
        $data = [
          'transactionReference' => $ref
        ];

        $response = $this->gateway()->fetchTransaction($data)->send();

        if ($response->isPaid() || $response->isPaidOut()) {
            $invitation = Invitation::whereAccountId($this->accountGateway->account_id)
                            ->whereTransactionReference($ref)
                            ->first();
            if ($invitation) {
              $this->invitation = $invitation;
              $this->createPayment($ref);
            }
        } else {
            // check if payment has failed
            $payment = Payment::whereAccountId($this->accountGateway->account_id)
                            ->whereTransactionReference($ref)
                            ->first();
            if ($payment) {
                $payment->markFailed($response->getStatus());
            }
            return false;
        }

        return RESULT_SUCCESS;
    }

}
