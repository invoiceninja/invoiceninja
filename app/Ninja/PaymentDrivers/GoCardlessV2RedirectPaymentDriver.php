<?php

namespace App\Ninja\PaymentDrivers;

use Session;

class GoCardlessV2RedirectPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = "\x00*\x00id";

    public function gatewayTypes()
    {
        $types = [
            GATEWAY_TYPE_BANK_TRANSFER,
            GATEWAY_TYPE_TOKEN,
        ];

        return $types;
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        if ($paymentMethod) {
            $data['mandate_reference'] = $paymentMethod->source_reference;
        }

        if ($ref = request()->redirect_flow_id) {
            $data['transaction_reference'] = $ref;
        }

        return $data;
    }

    protected function shouldCreateToken()
    {
        return false;
    }

    public function completeOffsitePurchase($input)
    {
        $details = $this->paymentDetails();
        $this->purchaseResponse = $response = $this->gateway()->completePurchase($details)->send();

        if (! $response->isSuccessful()) {
            return false;
        }

        $paymentMethod = $this->createToken();
        $payment = $this->completeOnsitePurchase(false, $paymentMethod);

        return $payment;
    }

    protected function creatingCustomer($customer)
    {
        $customer->token = $this->purchaseResponse->getCustomerId();

        return $customer;
    }

    protected function creatingPaymentMethod($paymentMethod)
    {
        $paymentMethod->source_reference = $this->purchaseResponse->getMandateId();
        $paymentMethod->payment_type_id = PAYMENT_TYPE_ACH;
        $paymentMethod->status = PAYMENT_METHOD_STATUS_VERIFIED;

        return $paymentMethod;
    }


}
