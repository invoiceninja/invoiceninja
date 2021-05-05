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


use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Braintree\CreditCard;
use App\PaymentDrivers\Braintree\PayPal;
use Illuminate\Http\Request;

class BraintreePaymentDriver extends BaseDriver
{
    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    /**
     * @var \Braintree\Gateway;
     */
    public $gateway;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::PAYPAL => PayPal::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_BRAINTREE;

    public function init(): void
    {
        $this->gateway = new \Braintree\Gateway([
            'environment' => $this->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production',
            'merchantId' => $this->company_gateway->getConfigField('merchantId'),
            'publicKey' => $this->company_gateway->getConfigField('publicKey'),
            'privateKey' => $this->company_gateway->getConfigField('privateKey'),
        ]);
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
            GatewayType::PAYPAL,
        ];
    }

    public function authorizeView($data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($data)
    {
        return $this->payment_method->authorizeResponse($data);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function findOrCreateCustomer()
    {
        $existing = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->company_gateway->id)
            ->where('client_id', $this->client->id)
            ->first();

        if ($existing) {
            return $this->gateway->customer()->find($existing->gateway_customer_reference);
        }

        $result = $this->gateway->customer()->create([
            'firstName' => $this->client->present()->name,
            'email' => $this->client->present()->email,
            'phone' => $this->client->present()->phone,
        ]);

        if ($result->success) {
            return $result->customer;
        }
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        try {
            $response = $this->gateway->transaction()->refund($payment->transaction_reference, $amount);
        } catch(\Exception $e) {
            // ..
        }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $invoice = Invoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Payment with no invoice for amount {$amount} for client {$this->client->present()->name()}";
        }

        $this->init();

        $result = $this->gateway->transaction()->sale([
            'amount' => $amount,
            'paymentMethodToken' => $cgt->token,
            'deviceData' => '',
            'options' => [
                'submitForSettlement' => true
            ],
        ]);

        if ($result->success) {
            $this->confirmGatewayFee();

            $data = [
                'payment_type' => PaymentType::parseCardType(strtolower($result->transaction->creditCard['cardType'])),
                'amount' => $amount,
                'transaction_reference' => $result->transaction->id,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $result, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_BRAINTREE,
                $this->client
            );

            return $payment;
        }

        if (! $result->success) {
            $this->unWindGatewayFees($payment_hash);

            PaymentFailureMailer::dispatch($this->client, $result->transaction->additionalProcessorResponse, $this->client->company, $this->payment_hash->data->amount_with_fee);

            $message = [
                'server_response' => $result,
                'data' => $this->payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_BRAINTREE,
                $this->client
            );

            return false;
        }
    }
}
