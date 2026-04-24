<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Jobs\Util\SystemLogger;
use App\PaymentDrivers\Payware\BankTransfer;
use App\PaymentDrivers\Payware\PaywareApi;
use App\Http\Requests\Payments\PaymentNotificationWebhookRequest;

class PaywarePaymentDriver extends BaseDriver
{
    public $refundable = false;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    public $payment_method;

    public static $methods = [
        GatewayType::MOBILE_PAYMENT => BankTransfer::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYWARE;

    private ?PaywareApi $api = null;

    public function gatewayTypes(): array
    {
        return [
            GatewayType::MOBILE_PAYMENT,
        ];
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    public function getApi(): PaywareApi
    {
        if ($this->api === null) {
            $this->api = new PaywareApi(
                $this->company_gateway->getConfigField('partnerId') ?: '',
                $this->company_gateway->getConfigField('vposId') ?: '',
                $this->company_gateway->getConfigField('paywarePublicKey') ?: '',
                (bool) $this->company_gateway->getConfigField('testMode'),
            );
        }

        return $this->api;
    }

    public function authorizeView(array $data)
    {
        // payware does not support payment method authorization
    }

    public function authorizeResponse($request)
    {
        // payware does not support payment method authorization
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        return [];
    }

    public function processWebhookRequest(PaymentNotificationWebhookRequest $request)
    {
        // Handle GET status check (polling from browser)
        if ($request->isMethod('GET') && $request->has('check_status')) {
            $hash = $request->input('payment_hash');
            $paymentHash = PaymentHash::where('hash', $hash)->first();

            if (!$paymentHash) {
                return response()->json(['status' => 'FAILED', 'message' => 'Payment hash not found']);
            }

            $data = (array) $paymentHash->data;
            $status = $data['payware_status'] ?? 'PENDING';

            $response = ['status' => $status];

            if ($status === 'CONFIRMED' && isset($data['payware_payment_id'])) {
                $response['redirect'] = route('client.payments.show', [
                    'payment' => $data['payware_payment_id'],
                ]);
            }

            if (in_array($status, ['DECLINED', 'FAILED', 'CANCELLED', 'EXPIRED'])) {
                $response['message'] = $data['payware_status_message'] ?? 'Payment was not completed.';
            }

            return response()->json($response);
        }

        $rawBody = file_get_contents('php://input');
        $headers = getallheaders();

        $authHeader = '';
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if (empty($authHeader)) {
            return response()->json(['error' => 'Missing Authorization header'], 401);
        }

        $api = $this->getApi();

        try {
            $webhookData = $api->validateWebhook($authHeader, $rawBody);
        } catch (\Exception $e) {
            nlog('payware webhook validation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 401);
        }

        if (empty($webhookData->passbackParams)) {
            return response()->json(['error' => 'Missing passbackParams'], 400);
        }

        $paymentHash = PaymentHash::where('hash', $webhookData->passbackParams)->first();

        if (!$paymentHash) {
            return response()->json(['error' => 'Payment hash not found'], 404);
        }

        $hashData = (array) $paymentHash->data;
        $status = $webhookData->status ?? 'UNKNOWN';
        $transactionId = $webhookData->transactionId ?? '';
        $statusMessage = $webhookData->statusMessage ?? '';

        $hashData['payware_status'] = $status;
        $hashData['payware_status_message'] = $statusMessage;

        if ($status === 'CONFIRMED') {
            $this->setPaymentMethod(GatewayType::MOBILE_PAYMENT);
            $this->payment_hash = $paymentHash;

            $invoice = $paymentHash->fee_invoice;

            if ($invoice) {
                $this->client = $invoice->client;

                $paymentData = [
                    'payment_method' => GatewayType::MOBILE_PAYMENT,
                    'payment_type' => PaymentType::BANK_TRANSFER,
                    'amount' => $paymentHash->data->amount_with_fee,
                    'gateway_type_id' => GatewayType::MOBILE_PAYMENT,
                    'transaction_reference' => $transactionId,
                ];

                $payment = $this->createPayment($paymentData, Payment::STATUS_COMPLETED);

                $hashData['payware_payment_id'] = $payment->hashed_id;

                SystemLogger::dispatch(
                    ['response' => (array) $webhookData, 'data' => $paymentData],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_PAYWARE,
                    $this->client,
                    $this->client->company,
                );

                nlog('payware: Payment confirmed - ' . $transactionId);
            }
        } elseif (in_array($status, ['DECLINED', 'FAILED', 'CANCELLED', 'EXPIRED'])) {
            $client = $paymentHash->fee_invoice?->client;

            if ($client) {
                SystemLogger::dispatch(
                    ['response' => (array) $webhookData, 'data' => ['status' => $status, 'message' => $statusMessage]],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_PAYWARE,
                    $client,
                    $client->company,
                );
            }

            nlog('payware: Payment ' . strtolower($status) . ' - ' . $transactionId . ($statusMessage ? ' - ' . $statusMessage : ''));
        }

        $paymentHash->data = $hashData;
        $paymentHash->save();

        return response()->json(['status' => 'ok']);
    }

    public function getClientRequiredFields(): array
    {
        return [];
    }
}
