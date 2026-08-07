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
use App\Http\Requests\Payments\PaymentNotificationWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\ChipInAsia\Hosted;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ChipInAsiaPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = false;

    public $payment_method;

    public static $methods = [
        GatewayType::HOSTED_PAGE => Hosted::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_CHIPINASIA;

    public function init(): self
    {
        return $this;
    }

    /**
     * Mandatory fields required by CHIP (e.g. email for client object).
     * Used by the required-client-info form on the payment layout.
     *
     * @return array<int, array{name: string, label: string, type: string, validation: string}>
     */
    public function getClientRequiredFields(): array
    {
        return [
            ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'],
        ];
    }

    public function gatewayTypes(): array
    {
        $types = [];
        if (($this->client->currency()->code ?? '') === 'MYR') {
            $types[] = GatewayType::HOSTED_PAGE;
        }

        return $types;
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
     * Refund a CHIP payment via POST /purchases/{id}/refund/
     * Amount in minor units (cents); omit for full refund.
     *
     * @return array{transaction_reference: string|null, transaction_response: string, success: bool, description: string, code: int|string, amount?: float}
     */
    public function refund(Payment $payment, $amount, $return_client_response = false): array
    {
        $this->init();
        $purchaseId = $payment->transaction_reference;
        if (empty($purchaseId)) {
            return [
                'transaction_reference' => null,
                'transaction_response' => '',
                'success' => false,
                'description' => 'Missing CHIP purchase id on payment.',
                'code' => 422,
            ];
        }

        $url = 'https://gate.chip-in.asia/api/v1/purchases/' . $purchaseId . '/refund/';
        $body = [];
        if ($amount > 0) {
            $body['amount'] = (int) round($amount * 100);
        }

        $response = Http::withToken($this->company_gateway->getConfigField('apiKey'))
            ->acceptJson()
            ->timeout(30)
            ->post($url, $body);

        if ($response->successful()) {
            $data = $response->json();
            SystemLogger::dispatch(
                ['server_response' => $data, 'payment_id' => $payment->id],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_CHIPINASIA,
                $this->client,
                $this->client->company,
            );
            // CHIP response: Payment object with id, payment.amount (minor units), payment.description, status
            $refundAmount = isset($data['payment']['amount'])
                ? (float) $data['payment']['amount'] / 100
                : $amount;

            return [
                'transaction_reference' => $data['id'] ?? $purchaseId,
                'transaction_response' => json_encode($data),
                'success' => true,
                'description' => $data['payment']['description'] ?? 'Refunded',
                'code' => 200,
                'amount' => $refundAmount,
            ];
        }

        $errorBody = $response->json();
        $description = $errorBody['__all__']['message'] ?? $errorBody['message'] ?? $response->body() ?: 'Refund failed';
        SystemLogger::dispatch(
            ['server_response' => $errorBody, 'payment_id' => $payment->id],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_CHIPINASIA,
            $this->client,
            $this->client->company,
        );

        return [
            'transaction_reference' => null,
            'transaction_response' => json_encode($errorBody),
            'success' => false,
            'description' => $description,
            'code' => $response->status(),
        ];
    }

    /**
     * Charge a saved CHIP card (recurring token): create a new purchase, then POST .../charge/ with the token.
     *
     * @return Payment the created payment on success
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash): Payment
    {
        $this->setPaymentHash($payment_hash);
        $this->setPaymentMethod(GatewayType::HOSTED_PAGE);

        $recurringToken = $cgt->gateway_customer_reference ?? $cgt->token;
        if (empty($recurringToken)) {
            $this->processInternallyFailedPayment($this, new PaymentFailed('CHIP token billing: no recurring token on file.'));
        }

        $newPurchaseId = $this->payment_method->createPurchaseForTokenCharge();
        $chargeResponse = $this->payment_method->chargeWithToken($newPurchaseId, $recurringToken);

        $payment = $this->payment_method->createPaymentFromCallback($chargeResponse);
        $payment_hash->payment_id = $payment->id;
        $payment_hash->save();

        return $payment;
    }

    /**
     * When the client removes the payment method in the client portal, tell CHIP to delete the
     * recurring token so the purchase id can no longer be used for token billing.
     *
     * @see https://docs.chip-in.asia/chip-collect/api-reference/purchases/delete-recurring-token
     */
    public function detach(ClientGatewayToken $token): bool
    {
        $purchaseId = $token->token ?? '';
        if ($purchaseId === '') {
            return true;
        }

        if (! $this->payment_method) {
            $this->setPaymentMethod($token->gateway_type_id);
        }

        $this->payment_method->deleteRecurringToken($purchaseId);

        return true;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * Directly create a CHIP purchase and return the checkout URL.
     */
    public function processPaymentViewData(array $data): array
    {
        $data = $this->payment_method->paymentData($data);
        $data['redirect_to_gateway_url'] = $data['redirect_url'];

        return $data;
    }

    /**
     * Handle CHIP success_callback: verify X-Signature with public key, then create payment if status is paid.
     */
    public function processWebhookRequest(PaymentNotificationWebhookRequest $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Signature');

        $this->setPaymentMethod(GatewayType::HOSTED_PAGE);

        if (! $this->payment_method->verifyCallbackSignature($rawBody, $signature ?? '')) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $purchase = json_decode($rawBody, true);
        if (! is_array($purchase)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $reference = $purchase['reference'] ?? null;
        if (! $reference) {
            return response()->json(['message' => 'Missing reference'], 400);
        }

        $payment_hash = PaymentHash::where('hash', $reference)->first();
        if (! $payment_hash) {
            return response()->json(['message' => 'Payment hash not found'], 404);
        }

        $this->setPaymentHash($payment_hash);
        $this->setClient($payment_hash->fee_invoice->client);

        $purchaseId = $purchase['id'] ?? null;
        if (! $purchaseId) {
            return response()->json(['message' => 'Missing purchase id'], 400);
        }

        $existingPayment = Payment::where('transaction_reference', (string) $purchaseId)
            ->where('client_id', $this->client->id)
            ->first();

        $chipStatus = strtolower((string) ($purchase['status'] ?? ''));

        // Drive an existing pending payment to its terminal state. This
        // handles the case where tokenBilling created a pending record
        // (because /charge/ returned 'pending_charge'), and chip now
        // sends the authoritative 'paid' or failure event. A 'paid'
        // webhook with no existing payment falls through to the create
        // path below.
        if ($existingPayment) {
            $this->payment_method->transitionPaymentStatus($existingPayment, $chipStatus);
            return response()->json([], 200);
        }

        if ($chipStatus !== 'paid') {
            // No existing payment and chip says it's not paid yet
            // (e.g. 'pending_charge' arriving for the first time, or
            // an error event). We can't create a payment in either
            // case: 'pending_charge' is handled in tokenBilling's
            // direct call to createPaymentFromCallback, and errors
            // should not produce a Payment at all.
            return response()->json([], 200);
        }

        $this->payment_method->createPaymentFromCallback($purchase);

        return response()->json([], 200);
    }

    /**
     * Perform a health check by fetching the public key from CHIP.
     * This verifies that the API Key is valid and the server is reachable.
     */
    public function auth(): string
    {
        try {
            $response = Http::withToken($this->company_gateway->getConfigField('apiKey'))
                ->acceptJson()
                ->timeout(15)
                ->get('https://gate.chip-in.asia/api/v1/public_key/');

            if ($response->successful()) {
                return 'Connection Successful';
            }

            $error = $response->json();

            return 'Connection Failed: ' . ($error['message'] ?? $error['error'] ?? 'Check your API Key');
        } catch (\Exception $e) {
            return 'Connection Error: ' . $e->getMessage();
        }
    }
}
