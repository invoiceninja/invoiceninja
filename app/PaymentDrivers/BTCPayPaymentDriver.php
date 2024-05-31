<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Utils\Traits\MakesHash;
use App\Models\GatewayType;
use App\PaymentDrivers\BTCPay\BTCPay;
use App\Models\SystemLog;
use App\Models\Payment;
use App\Exceptions\PaymentFailed;

use BTCPayServer\Client\Webhook;

class BTCPayPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = false; //does this gateway support token billing?

    public $can_authorise_credit_card = false; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CRYPTO => BTCPay::class, //maps GatewayType => Implementation class
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_CHECKOUT; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public $btcpay_url  = "";
    public $api_key  = "";
    public $store_id = "";
    public $webhook_secret = "";
    public $btcpay;


    public function init()
    {
        $this->btcpay_url = $this->company_gateway->getConfigField('btcpayUrl');
        $this->api_key = $this->company_gateway->getConfigField('apiKey');
        $this->store_id = $this->company_gateway->getConfigField('storeId');
        $this->webhook_secret = $this->company_gateway->getConfigField('webhookSecret');
        return $this; /* This is where you boot the gateway with your auth credentials*/
    }

    /* Returns an array of gateway types for the payment gateway */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CRYPTO;

        return $types;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processWebhookRequest()
    {
        $webhook_payload = file_get_contents('php://input');
        //file_put_contents("/home/claude/invoiceninja/storage/my.log", $webhook_payload);

        $btcpayRep = json_decode($webhook_payload);
        if ($btcpayRep == null) {
            throw new PaymentFailed('Empty data');
        }
        if (true === empty($btcpayRep->invoiceId)) {
            throw new PaymentFailed(
                'Invalid BTCPayServer payment notification- did not receive invoice ID.'
            );
        }
        if (str_starts_with($btcpayRep->invoiceId, "__test__") || $btcpayRep->type == "InvoiceCreated") {
            return;
        }

        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'btcpay-sig') {
                $sig = $value;
            }
        }

        $this->init();
        $webhookClient = new Webhook($this->btcpay_url, $this->api_key);

        if (!$webhookClient->isIncomingWebhookRequestValid($webhook_payload, $sig, $this->webhook_secret)) {
            throw new \RuntimeException(
                'Invalid BTCPayServer payment notification message received - signature did not match.'
            );
        }

        /** @var \App\Models\Payment $payment **/
        $payment = Payment::find($btcpayRep->metafata->paymentID);
        switch ($btcpayRep->type) {
            case "InvoiceExpired":
                $payment->status_id = Payment::STATUS_CANCELLED;
                break;
            case "InvoiceInvalid":
                $payment->status_id = Payment::STATUS_FAILED;
                break;
            case "InvoiceSettled":
                $payment->status_id = Payment::STATUS_COMPLETED;
                break;
        }
        $payment->save();
    }


    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->setPaymentMethod(GatewayType::CRYPTO);
        return $this->payment_method->refund($payment, $amount); //this is your custom implementation from here
    }
}
