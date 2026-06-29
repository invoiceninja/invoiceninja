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

namespace App\PaymentDrivers\ChipInAsia;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\ChipInAsiaPaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class Hosted implements MethodInterface, LivewireMethodInterface
{
    protected ChipInAsiaPaymentDriver $driver;

    private const API_BASE_URL = 'https://gate.chip-in.asia/api/v1';

    public function __construct(ChipInAsiaPaymentDriver $driver)
    {
        $this->driver = $driver;
        $this->driver->init();
    }

    public function authorizeView(array $data): View
    {
        $data['gateway'] = $this->driver;
        return render('gateways.chipinasia.hosted.authorize', $data);
    }

    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Directly create a CHIP purchase and redirect OR show "Pay Now" if required fields form is needed.
     */
    public function paymentView(array $data): View|RedirectResponse
    {
        $data['gateway'] = $this->driver;
        $result = $this->paymentData($data);
        $checkout_url = $result['redirect_url'] ?? null;

        if (empty($checkout_url)) {
            throw new PaymentFailed(ctrans('texts.payment_error'));
        }

        if ($this->driver->company_gateway->always_show_required_fields ?? false) {
            $data['redirect_to_gateway_url'] = $checkout_url;
            return render('gateways.chipinasia.hosted.pay', $data);
        }

        return redirect()->away($checkout_url);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        // CHIP *_redirect URLs do not include purchase id; only success_callback (webhook) sends JSON with "id".
        // We store chip_purchase_id when creating the purchase and use it here for the redirect return.
        $purchaseId = $this->driver->payment_hash->data->chip_purchase_id ?? null;

        if (empty($purchaseId)) {
            $this->driver->sendFailureMail('Missing chip_purchase_id in payment hash (CHIP redirect does not pass id).');
            throw new PaymentFailed('Invalid return from payment gateway. Please contact support.');
        }

        // If webhook already created the payment, redirect to it instead of recreating.
        $existingPayment = Payment::where('transaction_reference', $purchaseId)
            ->where('client_id', $this->driver->client->id)
            ->first();

        if ($existingPayment) {
            return redirect()->route('client.payments.show', ['payment' => $this->driver->encodePrimaryKey($existingPayment->id)]);
        }

        $purchase = $this->getPurchase($purchaseId);

        if (! $purchase) {
            $this->driver->sendFailureMail('Could not verify payment with CHIP.');
            throw new PaymentFailed('Could not verify payment. Please contact support.');
        }

        $status = $purchase['status'] ?? null;

        if ($status === 'paid') {
            return $this->processSuccessfulPayment($purchase);
        }

        $message = 'Payment was not completed.';
        if (isset($purchase['transaction_data']['attempts'][0]['error']['message'])) {
            $message = $purchase['transaction_data']['attempts'][0]['error']['message'];
        }
        $this->processUnsuccessfulPayment($message, $purchaseId);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.chipinasia.hosted.pay_livewire';
    }

    public function paymentData(array $data): array
    {
        $returnUrl = route('client.payments.response.get', [], true);
        $returnUrl .= '?payment_hash=' . $this->driver->payment_hash->hash;
        $returnUrl .= '&company_gateway_id=' . $this->driver->company_gateway->id;
        $returnUrl .= '&payment_method_id=' . GatewayType::HOSTED_PAGE;

        $contact = $this->resolveContact();
        $client = $this->driver->client;

        $amountWithFee = (float) $this->driver->payment_hash->data->amount_with_fee;

        // CHIP only supports MYR
        $amountCents = (int) round($amountWithFee * 100);

        // Reuse existing purchase if it is still valid (not expired/pending_execute and created < 10 min ago).
        $existingPurchaseId = $this->driver->payment_hash->data->chip_purchase_id ?? null;
        if ($existingPurchaseId) {
            $existing = $this->getPurchase($existingPurchaseId);
            if ($existing) {
                $status = $existing['status'] ?? '';
                $createdOn = $existing['created_on'] ?? 0;
                $tenMinutesAgo = now()->subMinutes(10)->timestamp;
                if ($status === 'expired') {
                    $isReusable = false;
                } elseif ($status === 'pending_execute') {
                    $isReusable = $createdOn >= $tenMinutesAgo;
                } else {
                    $isReusable = true;
                }

                if ($isReusable) {
                    $checkoutUrl = $existing['checkout_url'] ?? null;
                    if ($checkoutUrl) {
                        $data['redirect_url'] = $checkoutUrl;
                        $data['gateway'] = $this->driver;
                        return $data;
                    }
                }
            }
        }

        $purchasePayload = [
            'products' => [
                [
                    'name' => $this->driver->getDescription(true),
                    'price' => $amountCents,
                ],
            ],
            'currency' => $client->currency()->code,
        ];

        $payload = array_filter([
            'brand_id' => $this->driver->company_gateway->getConfigField('brandId'),
            'client' => array_filter([
                'email' => $contact->email,
                'full_name' => trim($contact->first_name . ' ' . $contact->last_name) ?: $client->name ?? '',
                'phone' => $client->phone ?? '',
            ]),
            'purchase' => $purchasePayload,
            'reference' => $this->driver->payment_hash->hash,
            'success_redirect' => $returnUrl,
            'failure_redirect' => $returnUrl,
            'cancel_redirect' => $returnUrl,
            'success_callback' => $this->driver->genericWebhookUrl(),
        ]);

        // Token billing: "always" sends force_recurring + whitelist. "off" sends nothing and we never store token.
        // For always, optin, optout we set request_recurring_token so we store the token when CHIP returns it; for off we do not.
        $tokenBilling = $this->driver->company_gateway->token_billing ?? 'off';
        if ($tokenBilling === 'always') {
            $payload['force_recurring'] = true;
            $payload['payment_method_whitelist'] = ['visa', 'mastercard', 'maestro'];
        }
        if (in_array($tokenBilling, ['always', 'optin', 'optout'], true)) {
            $this->driver->payment_hash->withData('request_recurring_token', true);
        }
        // off: do not set force_recurring, payment_method_whitelist, or request_recurring_token

        $response = $this->chipRequest('POST', '/purchases/', $payload);

        if ($response->successful()) {
            $body = $response->json();
            $checkoutUrl = $body['checkout_url'] ?? null;
            $purchaseId = $body['id'] ?? null;
            if ($checkoutUrl) {
                if ($purchaseId) {
                    $this->driver->payment_hash->withData('chip_purchase_id', $purchaseId);
                }
                $data['redirect_url'] = $checkoutUrl;
                $data['gateway'] = $this->driver;
                return $data;
            }
        }

        $errorBody = $response->json();
        $error = $errorBody['message'] ?? $errorBody['errors'][0]['message'] ?? $response->body() ?: 'Failed to create payment.';
        $this->driver->sendFailureMail($error);
        throw new PaymentFailed($error);
    }

    /**
     * Call CHIP API with Bearer token.
     * Payload keys with empty string values are omitted (CHIP should not receive them).
     */
    private function chipRequest(string $method, string $path, array $body = []): \Illuminate\Http\Client\Response
    {
        $url = self::API_BASE_URL . $path;
        $request = Http::withToken($this->driver->company_gateway->getConfigField('apiKey'))
            ->acceptJson()
            ->timeout(30);

        if ($method === 'GET') {
            return $request->get($url);
        }

        return $request->post($url, $body);
    }


    /**
     * GET /purchases/{id}/ to retrieve purchase status.
     */
    private function getPurchase(string $purchaseId): ?array
    {
        $response = $this->chipRequest('GET', '/purchases/' . $purchaseId . '/');

        if (! $response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Create a CHIP purchase for token billing (no redirect). Used to charge a saved card via POST .../charge/.
     *
     * @return string the new purchase id
     */
    public function createPurchaseForTokenCharge(): string
    {
        $payment_hash = $this->driver->payment_hash;
        $amountWithFee = (float) $payment_hash->data->amount_with_fee;
        $amountCents = (int) round($amountWithFee * 100);
        $contact = $this->resolveContact();
        $client = $this->driver->client;

        $payload = array_filter([
            'brand_id' => $this->driver->company_gateway->getConfigField('brandId'),
            'client' => array_filter([
                'email' => $contact->email,
                'full_name' => trim($contact->first_name . ' ' . $contact->last_name) ?: $client->name ?? '',
                'phone' => $client->phone ?? '',
            ]),
            'purchase' => [
                'products' => [
                    [
                        'name' => $this->driver->getDescription(true),
                        'price' => $amountCents,
                    ],
                ],
                'currency' => $client->currency()->code,
            ],
            'reference' => $payment_hash->hash,
            'success_callback' => $this->driver->genericWebhookUrl(),
        ]);

        $response = $this->chipRequest('POST', '/purchases/', $payload);
        if (! $response->successful()) {
            $errorBody = $response->json();
            $error = $errorBody['__all__']['message'] ?? $errorBody['message'] ?? $response->body() ?: 'Failed to create purchase for charge.';
            throw new PaymentFailed($error);
        }

        $body = $response->json();
        $purchaseId = $body['id'] ?? null;
        if (empty($purchaseId)) {
            throw new PaymentFailed('CHIP did not return a purchase id.');
        }

        return $purchaseId;
    }

    /**
     * Resolve the contact used to build the CHIP purchase payload.
     *
     * Uses the driver-level contact (invitation, then auth guard) via
     * BaseDriver::getContact(). The contact is the one tied to the
     * active payment — usually the InvoiceInvitation's contact, or the
     * currently authenticated client portal user.
     *
     * Throws PaymentFailed if no contact with an email is found.
     * Centralising the resolution here lets the payload builders treat
     * $contact as a real ClientContact with a guaranteed email, instead
     * of leaning on null-coalescing fallbacks that mask the
     * missing-contact case.
     *
     * Why no client-level fallback: a Client has many Contacts. Each
     * payment is tied to a specific contact via the InvoiceInvitation.
     * If that contact lacks an email, falling back to "the client's
     * first contact" silently routes the payment receipt and CHIP
     * customer object to a different person than the payer — a real
     * data-integrity and privacy bug. Better to fail loudly so the
     * merchant fixes the contact record.
     *
     * Replaces the previous ensureChipRequiredFields() helper, which
     * only validated the email after the fact; that pattern made the
     * "no contact" path implicit and review flagged it as fragile.
     */
    private function resolveContact(): \App\Models\ClientContact
    {
        $contact = $this->driver->getContact();
        if ($contact instanceof \App\Models\ClientContact && $contact->email) {
            return $contact;
        }

        throw new PaymentFailed(ctrans('texts.provide_email'));
    }

    /**
     * Charge a CHIP purchase using a recurring token (saved card). POST /purchases/{id}/charge/.
     *
     * @return array the response body on success
     */
    public function chargeWithToken(string $purchaseId, string $recurringToken): array
    {
        $response = $this->chipRequest('POST', '/purchases/' . $purchaseId . '/charge/', [
            'recurring_token' => $recurringToken,
        ]);

        if (! $response->successful()) {
            $errorBody = $response->json();
            $code = $errorBody['__all__']['code'] ?? '';
            $message = $errorBody['__all__']['message'] ?? $errorBody['message'] ?? $response->body() ?: 'Charge failed.';
            throw new PaymentFailed($message, $response->status());
        }

        return $response->json();
    }

    /**
     * POST /purchases/{id}/cancel/ to cancel a purchase and prevent future payment.
     */
    private function cancelPurchase(string $purchaseId): bool
    {
        $response = $this->chipRequest('POST', '/purchases/' . $purchaseId . '/cancel/');

        return $response->successful();
    }

    /**
     * POST /purchases/{id}/delete_recurring_token/ — delete the recurring token on CHIP so the
     * purchase id can no longer be used for token billing. Call when the client removes the payment method.
     *
     * @see https://docs.chip-in.asia/chip-collect/api-reference/purchases/delete-recurring-token
     */
    public function deleteRecurringToken(string $purchaseId): void
    {
        $this->chipRequest('POST', '/purchases/' . $purchaseId . '/delete_recurring_token/', []);
    }

    /**
     * Retrieve the public key for authenticating CHIP callback payloads (e.g. success_callback or webhooks).
     * Cached per company gateway forever (no TTL) so the API is only called once; the key does not change.
     * See: https://docs.chip-in.asia/chip-collect/api-reference/public-key/retrieve
     *
     * @return string|null PEM-encoded RSA public key, or null on failure
     */
    public function getWebhookPublicKey(): ?string
    {
        $cacheKey = 'chip_webhook_public_key_' . $this->driver->company_gateway->id;

        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $response = $this->chipRequest('GET', '/public_key/');
        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        if (is_string($body)) {
            $key = str_replace('\n', "\n", $body);
        } else {
            $raw = $body['public_key'] ?? $body['key'] ?? null;
            $key = is_string($raw) ? str_replace('\n', "\n", $raw) : null;
        }

        if (is_string($key)) {
            Cache::forever($cacheKey, $key);
        }

        return $key;
    }

    /**
     * Verify CHIP success_callback X-Signature (base64-encoded RSA PKCS#1 v1.5 signature of SHA256 digest of request body).
     * See: https://docs.chip-in.asia/chip-collect/overview/callbacks
     */
    public function verifyCallbackSignature(string $rawBody, string $signatureHeader): bool
    {
        if ($rawBody === '' || $signatureHeader === '') {
            return false;
        }

        $publicKeyPem = $this->getWebhookPublicKey();
        if (! $publicKeyPem) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            return false;
        }

        $signature = base64_decode($signatureHeader, true);
        if ($signature === false) {
            return false;
        }

        $verified = openssl_verify($rawBody, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($publicKey);

        return $verified === 1;
    }

    protected function processSuccessfulPayment(array $purchase): RedirectResponse
    {
        $this->createPaymentFromCallback($purchase);

        $purchaseId = $purchase['id'] ?? '';

        return redirect()->route('client.payments.show', ['payment' => $this->driver->encodePrimaryKey($this->driver->payment_hash->payment_id)]);
    }

    /**
     * Create a payment record from verified CHIP success_callback payload (no redirect).
     * When token_billing was requested and CHIP returned a recurring token, store it as ClientGatewayToken.
     *
     * Anti-tampering: the Payment record's amount comes from the trusted
     * payment_hash->amount_with_fee(), not from the chip payload. The
     * chip payload's purchase.total must match amount_with_fee() exactly
     * (after converting from minor units). If it doesn't, the payload is
     * either modified in flight or a different purchase than the one we
     * initiated — either way, refuse to create the payment and log the
     * discrepancy for the audit trail.
     *
     * Status handling: a 2xx response from CHIP does not necessarily mean
     * the charge is settled. /charge/ can return status='pending_charge'
     * when the acquirer has not yet confirmed — see
     * https://docs.chip-in.asia/chip-collect/api-reference/purchases/charge.
     * The Payment record's status must mirror the chip payload's status:
     *   - 'paid'           -> STATUS_COMPLETED
     *   - 'pending_charge' -> STATUS_PENDING (a later purchase.paid or
     *                         purchase.payment_failure webhook is the
     *                         authoritative signal and will transition it)
     *   - any other status (error, cancelled, expired, ...) -> throw
     *     PaymentFailed; we must not record a Payment in that case.
     *
     * @return Payment the created payment
     * @throws PaymentFailed if purchase.total is missing, the amount
     *   does not match amount_with_fee, or the status is not 'paid' or
     *   'pending_charge'.
     */
    public function createPaymentFromCallback(array $purchase): Payment
    {
        $purchaseId = $purchase['id'] ?? '';
        $expectedAmount = (float) $this->driver->payment_hash->amount_with_fee();

        $reportedTotalCents = $purchase['purchase']['total'] ?? null;
        if ($reportedTotalCents === null) {
            throw new PaymentFailed('CHIP purchase payload is missing the total.');
        }
        $reportedAmount = (float) $reportedTotalCents / 100;
        if (abs($reportedAmount - $expectedAmount) > 0.01) {
            SystemLogger::dispatch(
                [
                    'message' => 'CHIP payment amount mismatch — possible tampering',
                    'expected' => $expectedAmount,
                    'reported' => $reportedAmount,
                    'payment_hash' => $this->driver->payment_hash->hash,
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_CHIPINASIA,
                $this->driver->client,
                $this->driver->client->company,
            );
            throw new PaymentFailed('CHIP returned a different amount than expected.');
        }

        // Map chip's status to our Payment status. /charge/ can return
        // 'pending_charge' on a 2xx — we must NOT mark such a response
        // as completed, or our ledger will desync from CHIP's source
        // of truth.
        $chipStatus = strtolower((string) ($purchase['status'] ?? ''));
        $paymentStatus = match ($chipStatus) {
            'paid' => Payment::STATUS_COMPLETED,
            'pending_charge' => Payment::STATUS_PENDING,
            default => throw new PaymentFailed("CHIP returned unexpected status '{$chipStatus}'; cannot create payment."),
        };

        return DB::transaction(function () use ($purchase, $expectedAmount, $purchaseId, $paymentStatus, $chipStatus) {
            $existingPayment = Payment::where('transaction_reference', (string) $purchaseId)
                ->where('client_id', $this->driver->client->id)
                ->lockForUpdate()
                ->first();

            if ($existingPayment) {
                return $existingPayment;
            }

            $data = [
                'gateway_type_id' => GatewayType::HOSTED_PAGE,
                'amount' => $expectedAmount,
                'payment_type' => PaymentType::HOSTED_PAGE,
                'transaction_reference' => (string) $purchaseId,
                'idempotency_key' => substr((string) $purchaseId . '_' . $this->driver->payment_hash->hash, 0, 64),
            ];

            $payment = $this->driver->createPayment($data, $paymentStatus);

            $requestRecurring = $this->driver->payment_hash->data->request_recurring_token ?? false;
            $isRecurringToken = $purchase['purchase']['is_recurring_token'] ?? $purchase['is_recurring_token'] ?? false;
            if ($requestRecurring && $isRecurringToken && $purchaseId !== '') {
                $this->storeRecurringToken($purchaseId, $purchase);
            }

            SystemLogger::dispatch(
                ['response' => $purchaseId, 'data' => $data, 'chip_status' => $chipStatus, 'payment_status' => $paymentStatus],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_CHIPINASIA,
                $this->driver->client,
                $this->driver->client->company,
            );

            return $payment;
        });
    }

    /**
     * Drive a pending Payment to its terminal state based on a
     * subsequent chip webhook's status.
     *
     * Called by the webhook handler when a `purchase.paid` or
     * `purchase.payment_failure` event arrives for a payment that
     * was previously created with status='pending_charge' (e.g. from
     * the /charge/ endpoint's deferred response). Without this
     * transition, the Payment would stay pending forever.
     *
     * Returns true if the status was changed, false if no transition
     * was needed (already in the target state, or chip status is
     * still transitional).
     */
    public function transitionPaymentStatus(Payment $payment, string $chipStatus): bool
    {
        $chipStatus = strtolower($chipStatus);

        $newStatus = match (true) {
            $chipStatus === 'paid' && $payment->status_id === Payment::STATUS_PENDING => Payment::STATUS_COMPLETED,
            in_array($chipStatus, ['error', 'cancelled', 'expired'], true) && $payment->status_id === Payment::STATUS_PENDING => Payment::STATUS_FAILED,
            default => null,
        };

        if ($newStatus === null) {
            return false;
        }

        if($newStatus === Payment::STATUS_FAILED) {
            $payment->service()->deletePayment();   // restores invoice balance / paymentables
            $payment->status_id = Payment::STATUS_FAILED;
            $payment->save();
        }
        else {
            $payment->status_id = $newStatus;
            $payment->save();
        }
        
        SystemLogger::dispatch(
            [
                'message' => "CHIP payment transitioned: {$chipStatus}",
                'payment_id' => $payment->id,
                'from' => Payment::STATUS_PENDING,
                'to' => $newStatus,
            ],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_CHIPINASIA,
            $this->driver->client,
            $this->driver->client->company,
        );

        return true;
    }

    /**
     * Store CHIP recurring token (purchase id) as ClientGatewayToken for later token billing.
     */
    private function storeRecurringToken(string $purchaseId, array $purchase): void
    {
        $extra = $purchase['transaction_data']['extra'] ?? $purchase['transaction_data']['attempts'][0]['extra'] ?? [];
        $paymentMeta = new \stdClass();
        if (isset($extra['masked_pan'])) {
            $paymentMeta->last4 = substr(preg_replace('/\s/', '', $extra['masked_pan']), -4);
        }
        if (isset($extra['cardholder_name'])) {
            $paymentMeta->cardholder_name = $extra['cardholder_name'];
        }
        // brand: card network (e.g. Visa, Mastercard). CHIP sends card_brand; fallback to transaction_data.payment_method.
        $brand = $extra['card_brand'] ?? $extra['card_scheme']
            ?? $purchase['transaction_data']['payment_method'] ?? null;
        if (is_string($brand) && $brand !== '') {
            $paymentMeta->brand = ucfirst(strtolower($brand));
        }
        // scheme: card type (debit/credit). CHIP sends card_type.
        if (isset($extra['card_type']) && is_string($extra['card_type']) && $extra['card_type'] !== '') {
            $paymentMeta->scheme = ucfirst(strtolower($extra['card_type']));
        }
        if (isset($extra['expiry_month'])) {
            $paymentMeta->exp_month = (int) $extra['expiry_month'];
        }
        if (isset($extra['expiry_year'])) {
            $paymentMeta->exp_year = (int) $extra['expiry_year'];
        }

        $this->driver->storeGatewayToken(
            [
                'token' => $purchaseId,
                'payment_method_id' => GatewayType::HOSTED_PAGE,
                'payment_meta' => $paymentMeta,
            ],
            ['gateway_customer_reference' => $purchaseId]
        );
    }

    protected function processUnsuccessfulPayment(string $message, ?string $purchaseId = null): void
    {
        if ($purchaseId !== null && $purchaseId !== '') {
            $this->cancelPurchase($purchaseId);
        }

        $this->driver->sendFailureMail($message);

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_CHIPINASIA,
            $this->driver->client,
            $this->driver->client->company,
        );

        // Show generic message to user (same as Stripe, Razorpay, etc.); raw CHIP message is in mail/logs above.
        throw new PaymentFailed(ctrans('texts.payment_error'), 500);
    }
}
