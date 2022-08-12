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

use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Braintree\ACH;
use App\PaymentDrivers\Braintree\CreditCard;
use App\PaymentDrivers\Braintree\PayPal;
use Braintree\Gateway;
use Exception;
use Illuminate\Support\Facades\Validator;

class BraintreePaymentDriver extends BaseDriver
{
    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    /**
     * @var Gateway;
     */
    public Gateway $gateway;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::PAYPAL => PayPal::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_BRAINTREE;

    public function init(): void
    {
        $this->gateway = new Gateway([
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
        $types = [
            GatewayType::PAYPAL,
            GatewayType::CREDIT_CARD,
            GatewayType::BANK_TRANSFER,
        ];

        return $types;
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
            $address = $this->gateway->address()->create([
                'customerId' => $result->customer->id,
                'firstName' => $this->client->present()->name,
                'streetAddress' => $this->client->address1,
                'postalCode' => $this->client->postal_code,
                'countryCodeAlpha2' => $this->client->country ? $this->client->country->iso_3166_2 : '',
            ]);

            return $result->customer;
        }
            //12-08-2022 catch when the customer is not created.
            $data = [
                'transaction_reference' => null,
                'transaction_response' => $result,
                'success' => false,
                'description' => 'Could not create customer',
                'code' => 500,
            ];

            SystemLogger::dispatch(['server_response' => $result, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_BRAINTREE, $this->client, $this->client->company);

    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        try {
            $response = $this->gateway->transaction()->refund($payment->transaction_reference, $amount);
        } catch (Exception $e) {
            $data = [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];

            SystemLogger::dispatch(['server_response' => null, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_BRAINTREE, $this->client, $this->client->company);

            return $data;
        }

        if ($response->success) {
            $data = [
                'transaction_reference' => $payment->transaction_reference,
                'transaction_response' => json_encode($response),
                'success' => (bool) $response->success,
                'description' => ctrans('texts.plan_refunded'),
                'code' => 0,
            ];

            SystemLogger::dispatch(['server_response' => $response, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_BRAINTREE, $this->client, $this->client->company);

            return $data;
        } else {
            $error = $response->errors->deepAll()[0];

            $data = [
                'transaction_reference' => null,
                'transaction_response' => $response->errors->deepAll(),
                'success' => false,
                'description' => $error->message,
                'code' => $error->code,
            ];

            SystemLogger::dispatch(['server_response' => $response, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_BRAINTREE, $this->client, $this->client->company);

            return $data;
        }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $invoice = Invoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();

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
                'submitForSettlement' => true,
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

            $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $result, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_BRAINTREE,
                $this->client,
                $this->client->company,
            );

            return $payment;
        }

        if (! $result->success) {
            $this->unWindGatewayFees($payment_hash);

            $this->sendFailureMail($result->transaction->additionalProcessorResponse);

            $message = [
                'server_response' => $result,
                'data' => $this->payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_BRAINTREE,
                $this->client,
                $this->client->company
            );

            return false;
        }
    }

    public function processWebhookRequest($request)
    {
        $validator = Validator::make($request->all(), [
            'bt_signature' => ['required'],
            'bt_payload' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $this->init();

        $webhookNotification = $this->gateway->webhookNotification()->parse(
            $request->input('bt_signature'), $request->input('bt_payload')
        );

        nlog('braintree webhook');

        // if($webhookNotification)
        //     nlog($webhookNotification->kind);

        // // Example values for webhook notification properties
        // $message = $webhookNotification->kind; // "subscription_went_past_due"
        // $message = $webhookNotification->timestamp->format('D M j G:i:s T Y'); // "Sun Jan 1 00:00:00 UTC 2012"

        return response()->json([], 200);
    }
}
