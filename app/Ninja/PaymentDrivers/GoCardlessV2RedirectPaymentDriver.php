<?php

namespace App\Ninja\PaymentDrivers;

class GoCardlessV2RedirectPaymentDriver extends BasePaymentDriver
{
    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        if ($ref = request()->redirect_flow_id) {
            $data['transaction_reference'] = $ref;
        }

        return $data;
    }    
}
