<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Helcim\CreditCard;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Http;

class HelcimPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_HELCIM;

    public function init()
    {
        return $this;
    }

    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }

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
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->client = $payment->client;

        $response = $this->gatewayRequest('/payment/refund', [
            'transactionId' => $payment->transaction_reference,
            'amount' => $amount,
        ]);

        if (isset($response['transactionId']) && in_array($response['status'] ?? '', ['APPROVED', 'PENDING'])) {
            $data = [
                'transaction_reference' => $response['transactionId'],
                'transaction_response' => json_encode($response),
                'success' => true,
                'description' => $response['message'] ?? 'Refund processed',
                'code' => $response['status'],
            ];

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->client,
                $this->client->company
            );

            return $data;
        }

        $data = [
            'transaction_reference' => $payment->transaction_reference,
            'transaction_response' => json_encode($response),
            'success' => false,
            'description' => $response['message'] ?? 'Refund failed',
            'code' => $response['status'] ?? 'FAILED',
        ];

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_HELCIM,
            $this->client,
            $this->client->company
        );

        return $data;
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $response = $this->gatewayRequest('/payment/purchase', [
            'cardToken' => $cgt->token,
            'amount' => $amount,
            'currency' => $this->client->currency()->code,
            'invoiceNumber' => $payment_hash->hash,
        ]);

        if (isset($response['transactionId']) && in_array($response['status'] ?? '', ['APPROVED'])) {
            $payment_record = [
                'amount' => $amount,
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'transaction_reference' => $response['transactionId'],
            ];

            $payment = $this->createPayment($payment_record, Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $payment_record],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->client,
                $this->client->company
            );

            return $payment;
        }

        $this->unWindGatewayFees($payment_hash);
        $this->sendFailureMail($response['message'] ?? 'Payment failed');

        $message = [
            'server_response' => $response,
            'data' => $payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_HELCIM,
            $this->client,
            $this->client->company
        );

        return false;
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        // Helcim webhook handling
        nlog('Helcim Webhook Received');
        
        $payload = $request->all();
        
        if (isset($payload['type'])) {
            match ($payload['type']) {
                'payment' => $this->handlePaymentWebhook($payload),
                'refund' => $this->handleRefundWebhook($payload),
                default => nlog("Helcim: Unknown webhook type {$payload['type']}"),
            };
        }

        return response()->json(['success' => true], 200);
    }

    private function handlePaymentWebhook($payload)
    {
        if (!isset($payload['transactionId'])) {
            return;
        }

        $payment = Payment::where('transaction_reference', $payload['transactionId'])
            ->where('company_id', $this->company_gateway->company_id)
            ->first();

        if ($payment && isset($payload['status'])) {
            if ($payload['status'] === 'APPROVED') {
                $payment->status_id = Payment::STATUS_COMPLETED;
                $payment->save();
            } elseif (in_array($payload['status'], ['DECLINED', 'FAILED'])) {
                $payment->status_id = Payment::STATUS_FAILED;
                $payment->save();
            }
        }
    }

    private function handleRefundWebhook($payload)
    {
        nlog('Helcim refund webhook processed');
        nlog($payload);
    }

    public function gatewayRequest(string $uri, array $data)
    {
        $api_token = $this->company_gateway->getConfigField('apiToken');
        $base_url = $this->company_gateway->getConfigField('testMode') 
            ? 'https://api.helcim.com/v2' 
            : 'https://api.helcim.com/v2';

        $response = Http::withToken($api_token)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post($base_url . $uri, $data);

        if ($response->successful()) {
            return $response->json();
        }

        return [
            'status' => 'FAILED',
            'message' => $response->json()['message'] ?? $response->body(),
        ];
    }

    public function auth(): string
    {
        try {
            $response = $this->gatewayRequest('/payment/verify', []);
            
            return isset($response['status']) || isset($response['message']) ? 'ok' : 'error';
        } catch (\Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }
}