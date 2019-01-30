<?php

namespace App\Ninja\PaymentDrivers;

class PaymillPaymentDriver extends BasePaymentDriver
{
    public function tokenize()
    {
        return true;
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        if ($paymentMethod) {
            return $data;
        }

        if (! empty($this->input['sourceToken'])) {
            $data['token'] = $this->input['sourceToken'];
            unset($data['card']);
        }

        return $data;
    }
}
