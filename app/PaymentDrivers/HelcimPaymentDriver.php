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

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailedMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Helcim\ACH;
use App\PaymentDrivers\Helcim\CreditCard;
use App\PaymentDrivers\Helcim\HelcimAchTransaction;
use App\PaymentDrivers\Helcim\HelcimApiException;
use Illuminate\Support\Facades\DB;
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

    public function initializeAchPaymentCheckout(string $expectedFingerprint): array
    {
        if (! $this->payment_method instanceof ACH) {
            throw new PaymentFailed('Helcim ACH is not the selected payment method.', 400);
        }

        return $this->payment_method->initializePaymentCheckout($expectedFingerprint);
    }

    /**
     * Refund a payment
     * Routes to the correct refund endpoint based on payment gateway type (card vs ACH)
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        // Ensure $this->client is set — when called from the admin panel the driver
        // may be instantiated without a client, so load it from the payment.
        if (! $this->client) {
            $this->client = $payment->client;
        }

        if (empty($payment->transaction_reference)) {
            $error = 'Missing transaction reference for refund';

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

        $amount = round((float) $amount, 2);

        // ACH refunds use a different endpoint: PUT /ach/transactions/{id}/refund
        if ($this->isAchPayment($payment)) {
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

    private function isAchPayment(Payment $payment): bool
    {
        if ((int) $payment->gateway_type_id === GatewayType::BANK_TRANSFER) {
            return true;
        }

        if ((int) $payment->type_id === PaymentType::ACH) {
            return true;
        }

        return stripos((string) $payment->transaction_reference, 'ach_') === 0;
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
     * Verify a Helcim transaction server-side by fetching it from the Helcim API.
     *
     * PCI COMPLIANCE: This is the authoritative tamper-proof check. After HelcimPay.js
     * returns a transactionId we re-fetch the transaction directly from Helcim's API
     * using our secret server-side API token and confirm that the amount, currency and
     * status match what the client claims. This prevents a malicious actor from
     * substituting a cheaper / already-processed transactionId in the POST body.
     *
     * @param  string|int $transactionId   The transactionId returned by HelcimPay.js
     * @param  float      $expectedAmount  Amount we expect the transaction to be for
     * @param  string     $expectedCurrency ISO currency code (e.g. 'USD')
     * @param  string     $kind            'card' or 'ach'
     * @return array                       The raw Helcim transaction object
     *
     * @throws \App\Exceptions\PaymentFailed  On mismatch or API error
     */
    public function verifyHelcimTransaction(
        string|int $transactionId,
        float $expectedAmount,
        string $expectedCurrency,
        string $kind = 'card'
    ): array {
        if ($kind === 'ach') {
            return $this->verifyAchTransaction(
                $transactionId,
                $expectedAmount,
                $expectedCurrency
            )->raw;
        }

        $endpoint = $kind === 'ach'
            ? "/ach/transactions/{$transactionId}"
            : "/card-transactions/{$transactionId}";

        $txn = $this->gatewayRequest($endpoint, [], 'GET');

        // Helcim may wrap the transaction in a 'data' key
        $txnData = $txn['data'] ?? $txn;

        // ── Status check ──────────────────────────────────────────────────────
        $status = strtoupper((string) (
            $txnData['status'] ??
            $txnData['statusAuth'] ??
            $txnData['transactionStatus'] ??
            ''
        ));

        $acceptedStatuses = ['APPROVED', 'PENDING', 'QUEUED', 'SUBMITTED', 'OPENED', 'CLEARED', 'SETTLED', 'COMPLETED', 'SUCCESS'];
        if ($status !== '' && ! in_array($status, $acceptedStatuses, true)) {
            throw new \App\Exceptions\PaymentFailed(
                "Helcim transaction {$transactionId} has status '{$status}' — not approved.",
                400
            );
        }

        // ── Amount check ──────────────────────────────────────────────────────
        $returnedAmount = (float) ($txnData['amount'] ?? $txnData['totalAmount'] ?? -1);

        // Allow a 1-cent tolerance for floating-point rounding
        if ($returnedAmount >= 0 && abs($returnedAmount - $expectedAmount) > 0.015) {
            SystemLogger::dispatch(
                [
                    'error' => 'Helcim transaction amount mismatch — possible tamper attempt',
                    'expected_amount' => $expectedAmount,
                    'returned_amount' => $returnedAmount,
                    'transaction_id' => $transactionId,
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->client,
                $this->client->company
            );

            throw new \App\Exceptions\PaymentFailed(
                "Helcim transaction amount mismatch: expected {$expectedAmount}, got {$returnedAmount}.",
                400
            );
        }

        // ── Currency check ────────────────────────────────────────────────────
        $returnedCurrency = strtoupper((string) ($txnData['currency'] ?? $txnData['currencyCode'] ?? ''));

        if ($returnedCurrency !== '' && $returnedCurrency !== strtoupper($expectedCurrency)) {
            throw new \App\Exceptions\PaymentFailed(
                "Helcim transaction currency mismatch: expected {$expectedCurrency}, got {$returnedCurrency}.",
                400
            );
        }

        return $txnData;
    }

    /**
     * Fetch and validate the authoritative state of an ACH transaction.
     */
    public function verifyAchTransaction(
        string|int $transactionId,
        float $expectedAmount,
        string $expectedCurrency,
        bool $requireAccepted = true
    ): HelcimAchTransaction {
        $response = $this->gatewayRequest("/ach/transactions/{$transactionId}", [], 'GET');
        $transaction = HelcimAchTransaction::from($response);

        return $this->validateAchTransaction(
            $transaction,
            (string) $transactionId,
            $expectedAmount,
            $expectedCurrency,
            $requireAccepted
        );
    }

    /**
     * Validate a transaction returned directly by a server-to-server ACH request.
     */
    public function validateAchTransaction(
        HelcimAchTransaction $transaction,
        ?string $expectedTransactionId,
        float $expectedAmount,
        string $expectedCurrency,
        bool $requireAccepted = true,
        bool $requireFinancialFields = true
    ): HelcimAchTransaction {
        if (! $transaction->transactionId) {
            throw new PaymentFailed('Helcim ACH response did not include a transactionId.', 400);
        }

        if ($expectedTransactionId !== null && $transaction->transactionId !== $expectedTransactionId) {
            throw new PaymentFailed(
                "Helcim ACH transaction mismatch: expected {$expectedTransactionId}, got {$transaction->transactionId}.",
                400
            );
        }

        if (($requireFinancialFields && $transaction->amount === null)
            || ($transaction->amount !== null
                && (int) round($transaction->amount * 100) !== (int) round($expectedAmount * 100))) {
            throw new PaymentFailed(
                'Helcim ACH transaction amount mismatch: expected '
                    . $expectedAmount
                    . ', got '
                    . ($transaction->amount ?? 'no amount')
                    . '.',
                400
            );
        }

        if (($requireFinancialFields && $transaction->currency === null)
            || ($transaction->currency !== null && $transaction->currency !== strtoupper($expectedCurrency))) {
            throw new PaymentFailed(
                'Helcim ACH transaction currency mismatch: expected '
                    . strtoupper($expectedCurrency)
                    . ', got '
                    . ($transaction->currency ?? 'no currency')
                    . '.',
                400
            );
        }

        if ($requireAccepted && ! $transaction->isAccepted()) {
            throw new PaymentFailed(
                "Helcim ACH transaction {$transaction->transactionId} has status '{$transaction->statusDescription()}'.",
                400
            );
        }

        if (! $requireAccepted && ! ($transaction->isAccepted() || $transaction->isFailed())) {
            throw new PaymentFailed(
                "Helcim ACH transaction {$transaction->transactionId} has unknown status '{$transaction->statusDescription()}'.",
                400
            );
        }

        return $transaction;
    }

    /**
     * Reconcile a local ACH payment with Helcim's authoritative clearing state.
     */
    public function reconcileAchPayment(Payment $payment): string
    {
        // Once clearing has been confirmed, claims and reversals are handled
        // manually, consistently with the other payment gateways.
        if ($payment->status_id !== Payment::STATUS_PENDING || $payment->is_deleted) {
            return 'unchanged';
        }

        $this->client = $payment->client;
        $currency = $payment->currency->code;

        $transaction = $this->verifyAchTransaction(
            $payment->transaction_reference,
            (float) $payment->amount,
            $currency,
            false
        );

        return DB::transaction(function () use ($payment, $transaction): string {
            /** @var Payment|null $lockedPayment */
            $lockedPayment = Payment::withTrashed()
                ->where('id', $payment->id)
                ->where('company_gateway_id', $this->company_gateway->id)
                ->where('transaction_reference', $transaction->transactionId)
                ->lockForUpdate()
                ->first();

            if (! $lockedPayment) {
                return 'not_found';
            }

            if ($transaction->isCompleted()) {
                if ($lockedPayment->status_id === Payment::STATUS_PENDING && ! $lockedPayment->is_deleted) {
                    $lockedPayment->status_id = Payment::STATUS_COMPLETED;
                    $lockedPayment->saveQuietly();

                    return 'completed';
                }

                return 'unchanged';
            }

            if ($transaction->isFailed()) {
                if ($lockedPayment->status_id !== Payment::STATUS_PENDING || $lockedPayment->is_deleted) {
                    return 'unchanged';
                }

                $lockedPayment->loadMissing('client.company', 'invoices');
                $paymentHash = PaymentHash::where('payment_id', $lockedPayment->id)->first();
                $client = $lockedPayment->client;

                $lockedPayment->service()->deletePayment();
                $lockedPayment->status_id = Payment::STATUS_FAILED;
                $lockedPayment->save();

                PaymentFailedMailer::dispatch(
                    $paymentHash,
                    $client->company,
                    $client,
                    "Helcim ACH payment {$transaction->transactionId} failed with status {$transaction->statusDescription()}."
                );

                return 'failed';
            }

            return 'pending';
        });
    }

    /**
     * Make a request to the Helcim API
     *
     * SECURITY: This method handles all API communication server-side.
     * The API token is NEVER exposed to the frontend.
     *
     * Supports GET, POST, and PUT methods.
     */
    public function gatewayRequest(
        string $endpoint,
        array $data,
        string $method = 'POST',
        ?string $idempotencyKey = null
    )
    {
        $url = $this->getApiUrl() . $endpoint;

        $http = Http::withOptions(['verify' => true, 'allow_redirects' => false])
            ->withHeaders(['api-token' => $this->getApiToken()]);

        if (in_array($method, ['POST', 'PUT'])) {
            $idempotencyKey ??= \Illuminate\Support\Str::uuid()->toString();
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
            throw new HelcimApiException(
                'Helcim API returned error: ' . $errorMessage . ' (HTTP ' . $response->status() . ')',
                $response->status()
            );
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
     * Process a signed Helcim webhook and reconcile the referenced ACH transaction.
     * The webhook body is only a notification; payment state always comes from a
     * fresh server-to-server transaction lookup.
     */
    public function processWebhookRequest($request)
    {
        $rawBody = $request->getContent();

        $verifierToken = $this->company_gateway->getConfigField('webhookVerifierToken');
        if (! $verifierToken) {
            return response()->json(['message' => 'Webhook verification is not configured'], 401);
        }

        if (! $this->hasValidWebhookSignature($request, $rawBody, $verifierToken)) {
            SystemLogger::dispatch(
                ['error' => 'Helcim webhook signature mismatch'],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                null,
                $this->company_gateway->company
            );

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();

        if (empty($payload)) {
            return response()->json(['message' => 'Empty payload'], 200);
        }

        $transactionId = (string) ($payload['transactionId'] ?? $payload['transaction_id'] ?? $payload['id'] ?? '');

        if ($transactionId === '') {
            return response()->json(['message' => 'No transactionId in payload'], 200);
        }

        /** @var Payment|null $payment */
        $payment = Payment::withTrashed()
            ->where('transaction_reference', $transactionId)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->where('status_id', Payment::STATUS_PENDING)
            ->where('is_deleted', false)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 200);
        }

        try {
            $result = $this->reconcileAchPayment($payment);
            SystemLogger::dispatch(
                ['webhook_payload' => $payload, 'result' => $result, 'transaction_id' => $transactionId],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $payment->client,
                $payment->client->company
            );
        } catch (\Throwable $e) {
            SystemLogger::dispatch(
                ['webhook_payload' => $payload, 'error' => $e->getMessage(), 'transaction_id' => $transactionId],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $payment->client,
                $payment->client->company
            );

            return response()->json(['message' => 'Webhook reconciliation failed'], 500);
        }

        return response()->json(['message' => 'Webhook processed'], 200);
    }

    private function hasValidWebhookSignature($request, string $rawBody, string $verifierToken): bool
    {
        $signatureHeader = (string) $request->header('webhook-signature', '');
        $webhookId = (string) $request->header('webhook-id', '');
        $timestamp = (string) $request->header('webhook-timestamp', '');

        if ($signatureHeader === '' || $webhookId === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $secret = base64_decode($verifierToken, true);
        if ($secret === false) {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            "{$webhookId}.{$timestamp}.{$rawBody}",
            $secret,
            true
        ));

        preg_match_all('/(?:^|[\s,])v1,([^\s,]+)/', $signatureHeader, $matches);

        foreach ($matches[1] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
