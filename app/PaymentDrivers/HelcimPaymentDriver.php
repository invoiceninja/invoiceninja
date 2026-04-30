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

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Helcim\CreditCard;
use Illuminate\Support\Facades\Http;

class HelcimPaymentDriver extends BaseDriver
{
    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_HELCIM;

    /**
     * Helcim API base URLs
     */
    private const API_BASE_LIVE = 'https://api.helcim.com/v2';
    private const API_BASE_TEST = 'https://api.helcim.com/v2';

    /**
     * Get the Helcim API base URL based on test mode
     */
    public function getApiUrl(): string
    {
        return $this->company_gateway->getConfigField('testMode') 
            ? self::API_BASE_TEST 
            : self::API_BASE_LIVE;
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
        $types = [
            GatewayType::CREDIT_CARD,
        ];

        return $types;
    }

    /**
     * View for authorizing a credit card
     */
    public function authorizeView(array $data)
    {
        return $this->creditCard()->authorizeView($data);
    }

    /**
     * Process authorization response
     */
    public function authorizeResponse($request)
    {
        return $this->creditCard()->authorizeResponse($request);
    }

    /**
     * View for processing a payment
     */
    public function processPaymentView(array $data)
    {
        return $this->creditCard()->paymentView($data);
    }

    /**
     * Process payment response
     */
    public function processPaymentResponse($request)
    {
        return $this->creditCard()->paymentResponse($request);
    }

    /**
     * Refund a payment
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

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
     * Initialize a HelcimPay.js checkout session
     * 
     * PCI COMPLIANCE: This method creates a secure checkout session that
     * allows HelcimPay.js to handle card data collection without it touching our servers.
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
        // JSON encode the data (Helcim uses specific encoding)
        $jsonData = json_encode($data);
        
        // Calculate our hash
        $calculatedHash = hash('sha256', $jsonData . $secretToken);
        
        // Compare hashes
        return hash_equals($calculatedHash, $hash);
    }

    /**
     * Make a request to the Helcim API
     * 
     * SECURITY: This method handles all API communication server-side.
     * The API token is NEVER exposed to the frontend.
     */
    public function gatewayRequest(string $endpoint, array $data, string $method = 'POST')
    {
        $url = $this->getApiUrl() . $endpoint;

        $http = Http::withOptions(['verify' => true, 'allow_redirects' => false])
            ->withHeaders(['api-token' => $this->getApiToken()]);

        if ($method === 'POST') {
            $idempotencyKey = \Illuminate\Support\Str::uuid()->toString();
            $http = $http->withHeaders(['idempotency-key' => $idempotencyKey]);
            $response = $http->post($url, $data);
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
     * Process token billing (charge using saved payment method)
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->payment_hash = $payment_hash;
        $this->init();
        $this->setPaymentMethod($cgt->gateway_type_id);

        return $this->creditCard()->tokenBilling($cgt, $payment_hash);
    }

    /**
     * Initialize and return the credit card payment method
     */
    private function creditCard()
    {
        return new CreditCard($this);
    }

    /**
     * Process webhook from Helcim
     */
    public function processWebhookRequest($request)
    {
        // Helcim webhook processing can be implemented here if needed
        // For now, return a basic response
        return response()->json(['message' => 'Webhook received'], 200);
    }
}
