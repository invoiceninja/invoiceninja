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

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Revolut\Hosted;
use App\Utils\Traits\MakesHash;

class RevolutPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => Hosted::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_REVOLUT;

    /**
     * Initialise the Revolut driver and return self.
     */
    public function init(): self
    {
        return $this;
    }

    /**
     * Return the gateway types supported.
     */
    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }

    /**
     * Set the payment method for this request.
     */
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
     * Attempt a refund via the Revolut Merchant API.
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $order_id = $payment->transaction_reference;

        try {
            $response = $this->httpClient()
                ->post($this->apiUrl("/api/orders/{$order_id}/refund"), [
                    'json' => [
                        'amount' => $this->convertToRevolutAmount($amount),
                        'currency' => $this->client->getCurrencyCode(),
                    ],
                    'http_errors' => false,
                ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!is_array($body)) {
                $body = ['message' => 'Invalid JSON response from Revolut API'];
            }

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                \App\Jobs\Util\SystemLogger::dispatch(
                    ['response' => $body, 'data' => ['amount' => $amount, 'order_id' => $order_id]],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_REVOLUT,
                    $this->client,
                    $this->client->company,
                );

                return [
                    'transaction_reference' => $body['id'] ?? $order_id,
                    'transaction_response' => json_encode($body),
                    'success' => true,
                    'description' => $payment->number,
                    'code' => $response->getStatusCode(),
                ];
            }

            \App\Jobs\Util\SystemLogger::dispatch(
                ['response' => $body, 'data' => ['amount' => $amount, 'order_id' => $order_id]],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_REVOLUT,
                $this->client,
                $this->client->company,
            );

            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($body),
                'success' => false,
                'description' => $body['message'] ?? 'Refund failed',
                'code' => $response->getStatusCode(),
            ];
        } catch (\Exception $e) {
            \App\Jobs\Util\SystemLogger::dispatch(
                ['server_response' => $e->getMessage(), 'data' => ['amount' => $amount, 'order_id' => $order_id]],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_REVOLUT,
                $this->client,
                $this->client->company,
            );

            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode(['error' => $e->getMessage()]),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    /**
     * Token billing is not supported.
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        // Not supported by Revolut hosted checkout
    }

    /**
     * Webhook processing placeholder.
     */
    public function processWebhookRequest(): void
    {
        //
    }

    /**
     * Convert float amount to Revolut's integer minor-unit format (e.g. 10.00 → 1000).
     */
    public function convertToRevolutAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Get the Revolut API base URL (sandbox vs. production).
     */
    public function getApiBaseUrl(): string
    {
        return $this->company_gateway->getConfigField('testMode')
            ? 'https://sandbox-merchant.revolut.com'
            : 'https://merchant.revolut.com';
    }

    /**
     * Build a full API URL from a path.
     */
    public function apiUrl(string $path): string
    {
        return $this->getApiBaseUrl() . $path;
    }

    /**
     * Return a configured GuzzleHTTP client for Revolut API calls.
     */
    public function httpClient(): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->company_gateway->getConfigField('apiKey'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Revolut-Api-Version' => '2024-09-01',
            ],
        ]);
    }
}
