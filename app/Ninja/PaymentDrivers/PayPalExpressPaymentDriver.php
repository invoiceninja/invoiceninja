<?php

namespace App\Ninja\PaymentDrivers;

use App\Models\Payment;
use App\Models\PaymentMethod;

/**
 * Class PayPalExpressPaymentDriver
 */
class PayPalExpressPaymentDriver extends BasePaymentDriver
{
    /**
     * @return array
     */
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_PAYPAL
        ];
    }

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @return array
     */
    protected function paymentDetails(PaymentMethod $paymentMethod = null)
    {
        $data = parent::paymentDetails();

        $data['ButtonSource'] = 'InvoiceNinja_SP';

        return $data;
    }

    /**
     * @param Payment $payment
     * @param PaymentMethod $paymentMethod
     *
     * @return Payment
     */
    protected function creatingPayment(Payment $payment, PaymentMethod $paymentMethod)
    {
        $payment->payer_id = $this->input['PayerID'];

        return $payment;
    }
}
