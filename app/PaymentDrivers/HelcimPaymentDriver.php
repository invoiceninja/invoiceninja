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
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Helcim\CreditCard;

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
            'transactionId' => $payment->transaction_reference,
            'amount' => $amount,
        ], 'POST');

        if (isset($response['status']) && $response['status'] === 'APPROVED') {
            $data = [
                'transaction_reference' => $response['transactionId'] ?? '',
                'success' => true,
                'description' => $response['message'] ?? ctrans('texts.refund_successful'),
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

        $error = $response['message'] ?? ctrans('texts.refund_failed');
        
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
     * Process token billing (charge using saved payment method)
     */
    public function tokenBilling(ClientGatewayToken $cgt, float $amount)
    {
        $this->init();
        $this->setPaymentMethod($cgt->gateway_type_id);

        return $this->creditCard()->tokenBilling($cgt, $amount);
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
        $token = $this->getApiToken();

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Helcim API Error: ' . $error);
        }

        $responseData = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMessage = $responseData['message'] ?? 'Unknown error';
            throw new \Exception('Helcim API returned error: ' . $errorMessage . ' (HTTP ' . $httpCode . ')');
        }

        return $responseData;
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