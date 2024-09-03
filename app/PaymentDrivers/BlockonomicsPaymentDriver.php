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
use App\Models\PaymentHash;
use App\Models\GatewayType;
use App\PaymentDrivers\Blockonomics\Blockonomics;
use App\Models\SystemLog;
use App\Models\Payment;
use App\Models\Client;
use App\Exceptions\PaymentFailed;
use App\Models\PaymentType;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\Invoice;

class BlockonomicsPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false; //does this gateway support refunds?

    public $token_billing = false; //does this gateway support token billing?

    public $can_authorise_credit_card = false; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CRYPTO => Blockonomics::class, //maps GatewayType => Implementation class
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_CHECKOUT; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public const api_key  = "";
    public $blockonomics;
    public $BASE_URL = 'https://www.blockonomics.co';
    public $NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    public $PRICE_URL = 'https://www.blockonomics.co/api/price';
    public $SET_CALLBACK_URL = 'https://www.blockonomics.co/api/update_callback';
    public $GET_CALLBACKS_URL = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';


    public function get_callbacks($crypto)
    {
        $response = $this->get($GET_CALLBACKS_URL, $this->api_key);
        return $response;
    }

    public function get_callbackSecret()
    {
        return md5(uniqid(rand(), true));
    }

    public function init()
    {
        $response = $this->get_callbacks();
        $this->api_key = $this->company_gateway->getConfigField('apiKey');
        $this->callback_url = $this->company_gateway->getConfigField('callbackUrl');
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

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function processWebhookRequest()
    {
        $webhook_payload = file_get_contents('php://input');

        /** @var \stdClass $blockonomicsRep */
        $blockonomicsRep = json_decode($webhook_payload);
        if ($blockonomicsRep == null) {
            throw new PaymentFailed('Empty data');
        }
        if (true === empty($blockonomicsRep->invoiceId)) {
            throw new PaymentFailed(
                'Invalid payment notification- did not receive invoice ID.'
            );
        }
        if (
            str_starts_with($blockonomicsRep->invoiceId, "__test__")
            || $blockonomicsRep->type == "InvoiceProcessing"
            || $blockonomicsRep->type == "InvoiceCreated"
        ) {
            return;
        }

        $sig = '';
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'blockonomics-sig') {
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

        $this->setPaymentMethod(GatewayType::CRYPTO);
        $this->payment_hash = PaymentHash::whereRaw('BINARY `hash`= ?', [$blockonomicsRep->metadata->InvoiceNinjaPaymentHash])->firstOrFail();
        $StatusId = Payment::STATUS_PENDING;
        if ($this->payment_hash->payment_id == null) {
            
            $_invoice = Invoice::with('client')->withTrashed()->find($this->payment_hash->fee_invoice_id);

            $this->client = $_invoice->client;

            $dataPayment = [
                'payment_method' => $this->payment_method,
                'payment_type' => PaymentType::CRYPTO,
                'amount' => $_invoice->amount,
                'gateway_type_id' => GatewayType::CRYPTO,
                'transaction_reference' => $blockonomicsRep->invoiceId
            ];
            $payment = $this->createPayment($dataPayment, $StatusId);
        } else {
            /** @var \App\Models\Payment $payment */
            $payment = Payment::withTrashed()->find($this->payment_hash->payment_id);
            $StatusId =  $payment->status_id;
        }
        switch ($blockonomicsRep->type) {
            case "InvoiceExpired":
                $StatusId = Payment::STATUS_CANCELLED;
                break;
            case "InvoiceInvalid":
                $StatusId = Payment::STATUS_FAILED;
                break;
            case "InvoiceSettled":
                $StatusId = Payment::STATUS_COMPLETED;
                break;
        }
        if ($payment->status_id != $StatusId) {
            $payment->status_id = $StatusId;
            $payment->save();
        }
    }


    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->setPaymentMethod(GatewayType::CRYPTO);
        return $this->payment_method->refund($payment, $amount); //this is your custom implementation from here
    }
}
