<?php namespace App\Ninja\PaymentDrivers;


class PayPalExpressPaymentDriver extends BasePaymentDriver
{
    public function gatewayTypes()
    {
        return [
            GATEWAY_TYPE_PAYPAL
        ];
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails();

        $data['ButtonSource'] = 'InvoiceNinja_SP';
        $data['solutionType'] = 'Sole'; // show 'Pay with credit card' option

        return $data;
    }

    protected function creatingPayment($payment, $paymentMethod)
    {
        $payment->payer_id = $this->input['PayerID'];

        return $payment;
    }
}
