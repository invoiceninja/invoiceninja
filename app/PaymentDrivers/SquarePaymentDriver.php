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
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Square\CreditCard;
use App\Utils\Traits\MakesHash;
use Square\Http\ApiResponse;

class SquarePaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $square;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class, //maps GatewayType => Implementation class
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_SQUARE;

    public function init()
    {
        $this->square = new \Square\SquareClient([
            'accessToken' => $this->company_gateway->getConfigField('accessToken'),
            'environment' => $this->company_gateway->getConfigField('testMode') ? \Square\Environment::SANDBOX : \Square\Environment::PRODUCTION,
        ]);

        return $this; /* This is where you boot the gateway with your auth credentials*/
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
        return $this->payment_method->authorizeView($data); //this is your custom implementation from here
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);  //this is your custom implementation from here
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        $amount_money = new \Square\Models\Money();
        $amount_money->setAmount($this->convertAmount($amount));
        $amount_money->setCurrency($this->square_driver->client->currency()->code);

        $body = new \Square\Models\RefundPaymentRequest(\Illuminate\Support\Str::random(32), $amount_money, $payment->transaction_reference);

        /** @var ApiResponse */
        $response = $this->square->getRefundsApi()->refund($body);

        // if ($response->isSuccess()) {
        //     return [
        //         'transaction_reference' => $refund->action_id,
        //         'transaction_response' => json_encode($response),
        //         'success' => $checkout_payment->status == 'Refunded',
        //         'description' => $checkout_payment->status,
        //         'code' => $checkout_payment->http_code,
        //     ];
        // }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->init();

        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $amount = $this->convertAmount($amount);

        $invoice = Invoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Payment with no invoice for amount {$amount} for client {$this->client->present()->name()}";
        }

        $amount_money = new \Square\Models\Money();
        $amount_money->setAmount($amount);
        $amount_money->setCurrency($this->client->currency()->code);

        $body = new \Square\Models\CreatePaymentRequest($cgt->token, \Illuminate\Support\Str::random(32), $amount_money);
        $body->setCustomerId($cgt->gateway_customer_reference);

        /** @var ApiResponse */
        $response = $this->square->getPaymentsApi()->createPayment($body);
        $body = json_decode($response->getBody());

        if ($response->isSuccess()) {
            $amount = array_sum(array_column($this->payment_hash->invoices(), 'amount')) + $this->payment_hash->fee_total;

            $payment_record = [];
            $payment_record['amount'] = $amount;
            $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
            $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
            $payment_record['transaction_reference'] = $body->payment->id;

            $payment = $this->createPayment($payment_record, Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $payment_record],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_CHECKOUT,
                $this->client,
                $this->client->company,
            );

            return $payment;
        }

        $this->unWindGatewayFees($payment_hash);

        $this->sendFailureMail($body->errors[0]->detail);

        $message = [
            'server_response' => $response,
            'data' => $payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_SQUARE,
            $this->client,
            $this->client->company,
        );

        return false;
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {
    }

    public function getClientRequiredFields(): array
    {
        $fields = [];

        $fields[] = ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_contact_name) {
            $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_contact_email) {
            $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];
        }

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_billing_address) {
            $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
//            $fields[] = ['name' => 'client_address_line_2', 'label' => ctrans('texts.address2'), 'type' => 'text', 'validation' => 'nullable'];
            $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
//            $fields[] = ['name' => 'client_shipping_address_line_2', 'label' => ctrans('texts.shipping_address2'), 'type' => 'text', 'validation' => 'sometimes'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }

        return $fields;
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
}
