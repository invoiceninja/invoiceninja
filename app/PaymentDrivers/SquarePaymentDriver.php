<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Square\Http\ApiResponse;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\Square\CreditCard;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use Square\Models\Builders\RefundPaymentRequestBuilder;

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
        $this->client = $payment->client;

        $amount_money = new \Square\Models\Money();
        $amount_money->setAmount($this->convertAmount($amount));
        $amount_money->setCurrency($this->client->currency()->code);

        $body = RefundPaymentRequestBuilder::init(\Illuminate\Support\Str::random(32), $amount_money)
                ->paymentId($payment->transaction_reference)
                ->reason('Refund Request')
                ->build();

        $apiResponse = $this->square->getRefundsApi()->refundPayment($body);

        if ($apiResponse->isSuccess()) {

            $refundPaymentResponse = $apiResponse->getResult();

            nlog($refundPaymentResponse);

            /**
            * - `PENDING` - Awaiting approval.
            * - `COMPLETED` - Successfully completed.
            * - `REJECTED` - The refund was rejected.
            * - `FAILED` - An error occurred.
            */

            $status = $refundPaymentResponse->getRefund()->getStatus();

            if(in_array($status, ['COMPLETED', 'PENDING'])){

                $transaction_reference = $refundPaymentResponse->getRefund()->getId();

                $data = [
                    'transaction_reference' => $transaction_reference,
                    'transaction_response' => json_encode($refundPaymentResponse->getRefund()->jsonSerialize()),
                    'success' => true,
                    'description' => $refundPaymentResponse->getRefund()->getReason(),
                    'code' => $refundPaymentResponse->getRefund()->getReason(),
                ];

                SystemLogger::dispatch(
                    [
                        'server_response' => $data,
                        'data' => request()->all()
                    ],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_SQUARE,
                    $this->client,
                    $this->client->company
                );

                return $data;
            }
            elseif(in_array($status, ['REJECTED', 'FAILED'])) {

                $transaction_reference = $refundPaymentResponse->getRefund()->getId();

                $data = [
                    'transaction_reference' => $transaction_reference,
                    'transaction_response' => json_encode($refundPaymentResponse->getRefund()->jsonSerialize()),
                    'success' => false,
                    'description' => $refundPaymentResponse->getRefund()->getReason(),
                    'code' => $refundPaymentResponse->getRefund()->getReason(),
                ];

                SystemLogger::dispatch(
                    [
                        'server_response' => $data,
                        'data' => request()->all()
                    ],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_SQUARE,
                    $this->client,
                    $this->client->company
                );

                return $data;
            }

        } else {
            
            /** @var \Square\Models\Error $error */
            $error = end($apiResponse->getErrors());

            $data = [
                    'transaction_reference' => $payment->transaction_reference,
                    'transaction_response' => $error->jsonSerialize(),
                    'success' => false,
                    'description' => $error->getDetail(),
                    'code' => $error->getCode(),
                ];

                SystemLogger::dispatch(
                    [
                                        'server_response' => $data,
                                        'data' => request()->all()
                                    ],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_SQUARE,
                    $this->client,
                    $this->client->company
                );

            return $data;
        }

    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->init();

        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $amount = $this->convertAmount($amount);

        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();

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
        $body->setAmountMoney($amount_money);

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

    public function createWebhooks()
    {
        $this->init();

        $event_types = ['payment.created', 'payment.updated'];
        $subscription = new \Square\Models\WebhookSubscription();
        $subscription->setName('Invoice Ninja Webhook Subscription');
        $subscription->setEventTypes($event_types);
        $subscription->setNotificationUrl($this->company_gateway->webhookUrl());
        // $subscription->setApiVersion('2021-12-15');

        $body = new \Square\Models\CreateWebhookSubscriptionRequest($subscription);
        $body->setIdempotencyKey(\Illuminate\Support\Str::uuid());

        $api_response = $this->square->getWebhookSubscriptionsApi()->createWebhookSubscription($body);

        if ($api_response->isSuccess()) {
            $result = $api_response->getResult();
        } else {
            $errors = $api_response->getErrors();
        }

    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {

        // header('Content-Type: text/plain');
        // $webhook_payload = file_get_contents('php://input');

        // if($request->header('cko-signature') == hash_hmac('sha256', $webhook_payload, $this->company_gateway->company->company_key)) {
        //     CheckoutWebhook::dispatch($request->all(), $request->company_key, $this->company_gateway->id)->delay(10);
        // } else {
        //     nlog("Hash Mismatch = {$request->header('cko-signature')} ".hash_hmac('sha256', $webhook_payload, $this->company_gateway->company->company_key));
        //     nlog($request->all());
        // }

        // return response()->json(['success' => true]);


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
