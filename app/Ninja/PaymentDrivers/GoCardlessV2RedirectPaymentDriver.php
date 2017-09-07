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

    protected function creatingPayment($payment, $paymentMethod)
    {
        \Log::info(json_encode($this->purchaseResponse));
        //$payment->payment_status_id = $this->purchaseResponse['status'] == 'succeeded' ? PAYMENT_STATUS_COMPLETED : PAYMENT_STATUS_PENDING;

        return $payment;
    }

    public function handleWebHook($input)
    {
        \Log::info('handleWebHook... ' . $_SERVER['HTTP_WEBHOOK_SIGNATURE']);
        \Log::info(json_encode($input));

        /*
        // We recommend storing your webhook endpoint secret in an environment variable
        // for security, but you could include it as a string directly in your code
        $token = getenv("GC_WEBHOOK_SECRET");

        $raw_payload = file_get_contents('php://input');

        $headers = getallheaders();
        $provided_signature = $headers["Webhook-Signature"];

        $calculated_signature = hash_hmac("sha256", $raw_payload, $token);

        if ($provided_signature == $calculated_signature) {
            // Process the events...

            header("HTTP/1.1 200 OK");
        } else {
            header("HTTP/1.1 498 Invalid Token");
        }
        */
        
        $event = $this->gateway()->parseNotification(
                                        file_get_contents('php://input'),
                                        $_SERVER['HTTP_WEBHOOK_SIGNATURE']
                                    );

        \Log::info('event:');
        \Log::info(json_encode($event));
    }

}
