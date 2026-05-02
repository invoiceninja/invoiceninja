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

use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Helcim\ACH;
use App\PaymentDrivers\Helcim\CreditCard;
use Illuminate\Support\Facades\Http;

class HelcimPaymentDriver extends BaseDriver
{
    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_HELCIM;

    /**
     * Helcim API base URL
     */
    private const API_BASE = 'https://api.helcim.com/v2';

    /**
     * Get the Helcim API base URL
     */
    public function getApiUrl(): string
    {
        return self::API_BASE;
    }

    /**
     * Get the Helcim API token
     */
    public function getApiToken(): string
    {
        return $this->company_gateway->getConfigField('apiToken');
    }

    /**
     * Returns the gateway types supported by this driver
     */
    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
            GatewayType::BANK_TRANSFER,
        ];
    }

    /**
     * Set the active payment method class
     */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * View for authorizing a payment method
     */
    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    /**
     * Process authorization response
     */
    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    /**
     * View for processing a payment
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    /**
     * Process payment response
     */
    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    /**
     * Refund a payment
     * Routes to the correct refund endpoint based on payment gateway type (card vs ACH)
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        // ACH refunds use a different endpoint: PUT /ach/transactions/{id}/refund
        if ($payment->gateway_type_id == GatewayType::BANK_TRANSFER) {
            return $this->refundAch($payment, $amount, $return_client_response);
        }

        // Card refund: POST /payment/refund
        $response = $this->gatewayRequest('/payment/refund', [
            'originalTransactionId' => (int) $payment->transaction_reference,
            'amount' => $amount,
            'ipAddress' => request()->ip(),
            'ecommerce' => true,
        ], 'POST');

        if (isset($response['status']) && $response['status'] === 'APPROVED') {
            $data = [
                'transaction_reference' => $response['transactionId'] ?? '',
                'success' => true,
                'description' => $response['message'] ?? ctrans('texts.refunded'),
                'code' => $response['responseCode'] ?? '',
            ];

            if ($return_client_response) {
                return $data;
            }

            SystemLogger::dispatch(
                $data,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->client,
                $this->client->company
            );

            return $data;
        }

        $error = $response['message'] ?? ctrans('texts.refunded_payment');

        SystemLogger::dispatch(
            ['error' => $error, 'response' => $response],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_HELCIM,
            $this->client,
            $this->client->company
        );

        if ($return_client_response) {
            return [
                'transaction_reference' => '',
                'success' => false,
                'description' => $error,
                'code' => $response['responseCode'] ?? '',
            ];
        }

        throw new \Exception($error);
    }

    /**
     * Refund an ACH payment via PUT /ach/transactions/{id}/refund
     */
    private function refundAch(Payment $payment, $amount, $return_client_response = false)
    {
        $transactionId = (int) $payment->transaction_reference;

        $response = $this->gatewayRequest("/ach/transactions/{$transactionId}/refund", [
            'amount' => $amount,
        ], 'PUT');

        // ACH refund response is { "message": "Successfully refunded..." }
        if (isset($response['message'])) {
            $data = [
                'transaction_reference' => (string) $transactionId,
                'success' => true,
                'description' => $response['message'],
                'code' => '',
            ];

            if ($return_client_response) {
                return $data;
            }

            SystemLogger::dispatch(
                $data,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->client,
                $this->client->company
            );

            return $data;
        }

        $error = ctrans('texts.refunded_payment');

        SystemLogger::dispatch(
            ['error' => $error, 'response' => $response],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_HELCIM,
            $this->client,
            $this->client->company
        );

        if ($return_client_response) {
            return [
                'transaction_reference' => '',
                'success' => false,
                'description' => $error,
                'code' => '',
            ];
        }

        throw new \Exception($error);
    }

    /**
     * Process token billing (charge using saved payment method)
     * Routes to the correct method class based on gateway type
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->payment_hash = $payment_hash;
        $this->init();
        $this->setPaymentMethod($cgt->gateway_type_id);

        return $this->payment_method->tokenBilling($cgt, $payment_hash);
    }

    /**
     * Initialize a HelcimPay.js checkout session
     *
     * PCI COMPLIANCE: This method creates a secure checkout session that
     * allows HelcimPay.js to handle card/bank data collection without it touching our servers.
     */
    public function initializeHelcimPaySession(array $params): array
    {
        $endpoint = '/helcim-pay/initialize';
        $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();

        $response = Http::withOptions(['verify' => true, 'allow_redirects' => false])
            ->withHeaders([
                'api-token' => $this->getApiToken(),
                'idempotency-key' => $idempotencyKey,
            ])
            ->post($this->getApiUrl() . $endpoint, $params);

        if ($response->failed()) {
            $errorMessage = $response->json('errors') ?? $response->json('message') ?? 'Unknown error';
            throw new \Exception('HelcimPay.js initialization failed: ' . (is_array($errorMessage) ? json_encode($errorMessage) : $errorMessage) . ' (HTTP ' . $response->status() . ')');
        }

        return $response->json();
    }

    /**
     * Validate HelcimPay.js transaction response
     *
     * PCI COMPLIANCE: This validates that the transaction response from HelcimPay.js
     * hasn't been tampered with by comparing the hash.
     */
    public function validateHelcimPayResponse(array $data, string $hash, string $secretToken): bool
    {
        $jsonData = json_encode($data);
        $calculatedHash = hash('sha256', $jsonData . $secretToken);

        return hash_equals($calculatedHash, $hash);
    }

    /**
     * Make a request to the Helcim API
     *
     * SECURITY: This method handles all API communication server-side.
     * The API token is NEVER exposed to the frontend.
     *
     * Supports GET, POST, and PUT methods.
     */
    public function gatewayRequest(string $endpoint, array $data, string $method = 'POST')
    {
        $url = $this->getApiUrl() . $endpoint;

        $http = Http::withOptions(['verify' => true, 'allow_redirects' => false])
            ->withHeaders(['api-token' => $this->getApiToken()]);

        if (in_array($method, ['POST', 'PUT'])) {
            $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
            $http = $http->withHeaders(['idempotency-key' => $idempotencyKey]);

            $response = $method === 'PUT'
                ? $http->put($url, $data)
                : $http->post($url, $data);
        } else {
            $response = $http->get($url, $data);
        }

        if ($response->failed()) {
            $errorMessage = $response->json('errors') ?? $response->json('message') ?? 'Unknown error';
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }
            throw new \Exception('Helcim API returned error: ' . $errorMessage . ' (HTTP ' . $response->status() . ')');
        }

        return $response->json();
    }

    /**
     * Validate the API token against Helcim's API.
     * Called by the "Check Credentials" button in the admin UI.
     *
     * @return string 'ok' on success, error message on failure
     */
    public function auth(): string
    {
        try {
            $response = Http::withOptions(['verify' => true, 'allow_redirects' => false])
                ->withHeaders(['api-token' => $this->getApiToken()])
                ->get($this->getApiUrl() . '/customers', ['search-value' => 'ping', 'limit' => 1]);

            if ($response->status() === 401 || $response->status() === 403) {
                $error = $response->json('errors') ?? $response->json('message') ?? 'Invalid API token';
                return is_array($error) ? json_encode($error) : (string) $error;
            }

            return 'ok';
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Process webhook from Helcim
     */
    public function processWebhookRequest($request)
    {
        return response()->json(['message' => 'Webhook received'], 200);
    }
}
