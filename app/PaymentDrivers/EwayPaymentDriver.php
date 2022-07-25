<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Eway\CreditCard;
use App\PaymentDrivers\Eway\Token;
use App\Utils\Traits\MakesHash;

class EwayPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $eway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class, //maps GatewayType => Implementation class
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_EWAY; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public function init()
    {
        $apiKey = $this->company_gateway->getConfigField('apiKey');
        $apiPassword = $this->company_gateway->getConfigField('password');
        $apiEndpoint = $this->company_gateway->getConfigField('testMode') ? \Eway\Rapid\Client::MODE_SANDBOX : \Eway\Rapid\Client::MODE_PRODUCTION;
        $this->eway = \Eway\Rapid::createClient($apiKey, $apiPassword, $apiEndpoint);

        return $this;
    }

    /* Returns an array of gateway types for the payment gateway */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;

        return $types;
    }

    /* Sets the payment method initialized */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    /* We need PCI compliance prior to enabling this */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $refund = [
            'Refund' => [
                'TransactionID' => $payment->transaction_reference,
                'TotalAmount' => $this->convertAmount($amount),
            ],
        ];

        $response = $this->init()->eway->refund($refund);

        $transaction_reference = '';
        $refund_status = true;
        $refund_message = '';

        if ($response->TransactionStatus) {
            $transaction_reference = $response->TransactionID;
        } else {
            if ($response->getErrors()) {
                foreach ($response->getErrors() as $error) {
                    $refund_status = false;
                    $refund_message = \Eway\Rapid::getMessage($error);
                }
            } else {
                $refund_status = false;
                $refund_message = 'Sorry, your refund failed';
            }
        }

        return [
            'transaction_reference' => $response->TransactionID,
            'transaction_response' => json_encode($response),
            'success' => $refund_status,
            'description' => $refund_message,
            'code' => '',
        ];
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return (new Token($this))->tokenBilling($cgt, $payment_hash);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {
    }

    public function convertAmount($amount)
    {
        $precision = $this->client->currency()->precision;

        if ($precision == 0) {
            return $amount;
        }

        if ($precision == 1) {
            return $amount * 10;
        }

        if ($precision == 2) {
            return $amount * 100;
        }

        return $amount;
    }

    public function getClientRequiredFields(): array
    {
        $fields = [];
        $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];
        $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_billing_address) {
            $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_postal_code) {
            $fields[] = ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }

        return $fields;
    }
}
