<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\LawPay\ACH;
use App\PaymentDrivers\LawPay\CreditCard;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class LawPayPaymentDriver extends BaseDriver
{
    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_LAWPAY;

    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
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

    /**
     * Get the base URL for the 8am/LawPay API.
     * Test and live modes use the same URL — the secret key determines the mode.
     */
    public function baseUrl(): string
    {
        return 'https://api.8am.com';
    }

    /**
     * Make an authenticated API request to the 8am/LawPay API.
     */
    public function gatewayRequest(string $method, string $endpoint, array $payload = []): Response
    {
        $secretKey = $this->company_gateway->getConfigField('secretKey');

        $response = Http::withBasicAuth($secretKey, '')
            ->baseUrl($this->baseUrl())
            ->{$method}("/v1/{$endpoint}", $payload);

        return $response;
    }

    /**
     * Convert a decimal amount to integer cents for the 8am API.
     */
    public function convertToGatewayAmount(float $amount): int
    {
        return (int) round($amount * 100, 0);
    }

    /**
     * Convert from gateway integer cents to decimal amount.
     */
    public function convertFromGatewayAmount(int $amount): float
    {
        return round($amount / 100, 2);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $transaction_reference = $payment->transaction_reference;

        // Try void first (works on unsettled transactions, reverses processing fees)
        try {
            $response = $this->gatewayRequest('post', "transactions/{$transaction_reference}/void");

            if ($response->successful()) {
                SystemLogger::dispatch(
                    ['action' => 'void', 'server_response' => $response->json()],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    self::SYSTEM_LOG_TYPE,
                    $this->client,
                    $this->client->company,
                );

                return [
                    'transaction_reference' => $transaction_reference,
                    'transaction_response' => $response->json(),
                    'success' => true,
                    'description' => $payment->paymentables,
                    'code' => $response->status(),
                    'voided' => true,
                ];
            }
        } catch (\Throwable $e) {
            // Void failed (likely already settled), fall through to refund
        }

        // Fall back to refund for settled transactions
        try {
            $payload = [
                'amount' => $this->convertToGatewayAmount($amount),
            ];

            $response = $this->gatewayRequest('post', "charges/{$transaction_reference}/refund", $payload);

            if ($response->successful()) {
                SystemLogger::dispatch(
                    ['action' => 'refund', 'server_response' => $response->json()],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    self::SYSTEM_LOG_TYPE,
                    $this->client,
                    $this->client->company,
                );

                return [
                    'transaction_reference' => $transaction_reference,
                    'transaction_response' => $response->json(),
                    'success' => true,
                    'description' => $payment->paymentables,
                    'code' => $response->status(),
                ];
            }

            SystemLogger::dispatch(
                ['action' => 'refund', 'server_response' => $response->json()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                self::SYSTEM_LOG_TYPE,
                $this->client,
                $this->client->company,
            );

            return [
                'transaction_reference' => $transaction_reference,
                'transaction_response' => $response->json(),
                'success' => false,
                'description' => $payment->paymentables,
                'code' => 422,
            ];
        } catch (\Throwable $th) {
            SystemLogger::dispatch(
                ['action' => 'error', 'data' => $th->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                self::SYSTEM_LOG_TYPE,
                $this->client,
                $this->client->company,
            );

            return [
                'transaction_reference' => $transaction_reference,
                'transaction_response' => $th->getMessage(),
                'success' => false,
                'description' => $payment->paymentables,
                'code' => 500,
            ];
        }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $payload = [
            'amount' => $this->convertToGatewayAmount($amount),
            'method' => $cgt->token,
            'reference' => $payment_hash->hash,
        ];

        try {
            $response = $this->gatewayRequest('post', 'charges', $payload);
        } catch (\Throwable $e) {
            $this->processInternallyFailedPayment($this, $e);
            return;
        }

        if ($response->successful()) {
            $lawpay_response = $response->json();

            $is_card = $cgt->gateway_type_id == GatewayType::CREDIT_CARD;
            $status = $is_card ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING;
            $payment_type = $is_card ? PaymentType::CREDIT_CARD_OTHER : PaymentType::ACH;

            $data = [
                'payment_method' => $cgt->gateway_type_id,
                'payment_type' => $payment_type,
                'amount' => $amount,
                'transaction_reference' => $lawpay_response['id'] ?? $lawpay_response['transaction_id'] ?? '',
                'gateway_type_id' => $cgt->gateway_type_id,
            ];

            $payment = $this->createPayment($data, $status);
            $payment->meta = $cgt->meta;
            $payment->save();

            SystemLogger::dispatch(
                ['server_response' => $lawpay_response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                self::SYSTEM_LOG_TYPE,
                $this->client,
                $this->client->company,
            );

            return $payment;
        }

        $error_message = $response->json()['message'] ?? $response->json()['error'] ?? 'Payment failed';

        SystemLogger::dispatch(
            ['server_response' => $response->json(), 'data' => $payload],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            self::SYSTEM_LOG_TYPE,
            $this->client,
            $this->client->company,
        );

        $this->sendFailureMail($error_message);

        throw new PaymentFailed($error_message, 500);
    }

    public function processWebhookRequest(\App\Http\Requests\Payments\PaymentWebhookRequest $request)
    {
        $payload = $request->all();

        \App\PaymentDrivers\LawPay\Jobs\LawPayWebhook::dispatch(
            $payload,
            $request->company_key,
            $this->company_gateway->id
        )->delay(now()->addSeconds(5));

        return response()->json([], 200);
    }
}
