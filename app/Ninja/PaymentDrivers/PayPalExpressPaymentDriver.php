<?php

namespace App\Ninja\PaymentDrivers;

class PayPalExpressPaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_PAYPAL,
        ];
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails();

        $data['ButtonSource'] = 'InvoiceNinja_SP';
        $data['solutionType'] = 'Sole'; // show 'Pay with credit card' option
        $data['transactionId'] = $data['transactionId'] . '-' . time();

        return $data;
    }

    protected function creatingPayment($payment, $paymentMethod)
    {
        $payment->payer_id = $this->input['PayerID'];

        return $payment;
    }

    protected function paymentUrl($gatewayTypeAlias)
    {
        $url = parent::paymentUrl($gatewayTypeAlias);

        // PayPal doesn't allow being run in an iframe so we need to open in new tab
        if ($this->account()->iframe_url) {
            return 'javascript:window.open("' . $url . '", "_blank")';
        } else {
            return $url;
        }
    }

}
