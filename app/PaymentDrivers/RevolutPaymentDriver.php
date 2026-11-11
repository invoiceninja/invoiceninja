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
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Revolut\Hosted;
use App\PaymentDrivers\Revolut\MerchantApi;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Revolut Merchant API.
 *
 * Every payment is a Revolut order. The payment method classes only render
 * their page; the order logic is shared here: an order is created for the
 * payment hash (pageOrder), paid by the customer, then read back from the
 * API and recorded through recordOrder(). Returns and webhooks all end in
 * recordOrder(), which is idempotent. API calls live in MerchantApi.
 *
 * Order states (capture_mode is always automatic):
 *   pending     created, not paid yet
 *   processing  payment in progress
 *   authorised  authorised, automatic capture still to come
 *   completed   captured: the only state that counts as paid
 *   cancelled / failed
 *
 * @see https://developer.revolut.com/docs/merchant
 * @see https://developer.revolut.com/docs/guides/merchant/reference/order-lifecycle
 */
class RevolutPaymentDriver extends BaseDriver
{
    use MakesHash;

    /** Revolut rejects webhook timestamps older than five minutes; we do the same. */
    public const WEBHOOK_TOLERANCE_MS = 5 * 60 * 1000;

    /** Order states that may still turn into completed without customer action. */
    public const IN_FLIGHT_STATES = ['processing', 'authorised'];

    /** How long (seconds) an in-flight order is re-read while the customer waits. */
    public const POLL_ATTEMPTS = 5;

    public $refundable = true;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    public $payment_method;

    public static $methods = [
        GatewayType::HOSTED_PAGE => Hosted::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_REVOLUT;

    private ?MerchantApi $api = null;

    public function api(): MerchantApi
    {
        return $this->api ??= new MerchantApi($this->company_gateway);
    }

    public function gatewayTypes(): array
    {
        return [
            GatewayType::HOSTED_PAGE,
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

    /**
     * Validate the secret API key against the selected environment.
     */
    public function auth(): string
    {
        if (trim((string) $this->company_gateway->getConfigField('apiKey')) === '') {
            return 'error';
        }

        return $this->api()->credentialsWork() ? 'ok' : 'error';
    }

    /**
     * Refund (part of) a completed order.
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->client = $payment->client;

        $order_id = (string) $payment->transaction_reference;

        $response = $this->api()->refundOrder($order_id, [
            'amount' => $this->convertToRevolutAmount((float) $amount),
            'currency' => $payment->currency->code ?? $this->client->getCurrencyCode(),
            'description' => ctrans('texts.refund') . ' ' . $payment->number,
        ]);

        $body = MerchantApi::body($response);
        $success = $response?->successful() ?? false;

        $this->log(['response' => $body, 'data' => ['amount' => $amount, 'order_id' => $order_id]], $success);

        return [
            'transaction_reference' => $success ? ($body['id'] ?? $order_id) : null,
            'transaction_response' => json_encode($body),
            'success' => $success,
            'description' => $success ? ($body['id'] ?? $payment->number) : MerchantApi::errorMessage($body, 'Refund failed'),
            'code' => $response?->status() ?? 500,
        ];
    }

    /**
     * The hosted checkout page does not save payment methods.
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }

    /**
     * Receives Revolut order webhooks so payments are recorded even when the
     * customer never comes back from the payment page.
     *
     * The webhook body is only used to find the order: its state is always
     * read back from the API.
     *
     * @see https://developer.revolut.com/docs/guides/merchant/integrate-with-webhooks
     */
    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        $signing_secret = trim((string) $this->company_gateway->getConfigField('signingSecret'));

        if ($signing_secret === '') {
            nlog('Revolut webhook ignored: no signing secret configured');

            return response()->json([], 200);
        }

        if (! $this->verifyWebhookSignature(
            $request->getContent(),
            (string) $request->header('Revolut-Request-Timestamp'),
            (string) $request->header('Revolut-Signature'),
            $signing_secret,
        )) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = json_decode($request->getContent(), true);

        if (! is_array($event) || empty($event['order_id'])
            || ! in_array($event['event'] ?? null, ['ORDER_COMPLETED', 'ORDER_AUTHORISED', 'ORDER_CANCELLED', 'ORDER_FAILED'], true)) {
            return response()->json([], 200);
        }

        $reference = $event['merchant_order_ext_ref'] ?? null;

        $payment_hash = $reference ? PaymentHash::query()->where('hash', $reference)->first() : null;

        // Only act on orders this gateway created for that payment hash.
        if (! $payment_hash || ($payment_hash->data->order_id ?? null) !== $event['order_id'] || ! $payment_hash->fee_invoice) {
            return response()->json([], 200);
        }

        $this->setPaymentHash($payment_hash);
        $this->setClient($payment_hash->fee_invoice->client);

        $order = $this->api()->retrieveOrder($event['order_id']);

        if (! $order) {
            // Let Revolut retry later.
            return response()->json(['message' => 'Order not retrievable'], 500);
        }

        try {
            $this->recordOrder($order);
        } catch (PaymentFailed $e) {
            // Already logged; retrying will not change the outcome.
            nlog('Revolut webhook not recorded: ' . $e->getMessage());
        }

        return response()->json([], 200);
    }

    /**
     * Revolut-Signature is "v1=<hex>" (comma separated when the secret was
     * rotated), an HMAC-SHA256 of "v1.{timestamp}.{raw body}".
     */
    public function verifyWebhookSignature(string $raw_body, string $timestamp, string $signature_header, string $secret): bool
    {
        if ($timestamp === '' || ! ctype_digit($timestamp) || $signature_header === '') {
            return false;
        }

        if (abs((int) (microtime(true) * 1000) - (int) $timestamp) > self::WEBHOOK_TOLERANCE_MS) {
            return false;
        }

        $expected = hash_hmac('sha256', "v1.{$timestamp}.{$raw_body}", $secret);

        foreach (explode(',', $signature_header) as $part) {
            $part = trim($part);

            if (str_starts_with($part, 'v1=') && hash_equals($expected, strtolower(substr($part, 3)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The order a payment page uses: the one created on an earlier visit if it
     * can still be paid, else a new one.
     */
    public function pageOrder(int $gateway_type_id, array $extra = []): array
    {
        return $this->reusableOrder($gateway_type_id) ?? $this->createOrder($gateway_type_id, $extra);
    }

    /**
     * Create an order for the current payment hash.
     *
     * The payment hash is sent as merchant_order_data.reference so webhooks
     * can find it, and the order id and gateway type are stored on the hash
     * so returns and webhooks record the order against the right method.
     */
    public function createOrder(int $gateway_type_id, array $extra = []): array
    {
        $response = $this->api()->createOrder(array_merge([
            'amount' => $this->convertToRevolutAmount((float) $this->payment_hash->amount_with_fee()),
            'currency' => $this->client->getCurrencyCode(),
            'capture_mode' => 'automatic',
            'description' => mb_substr((string) $this->getDescription(false), 0, 1024),
            'merchant_order_data' => ['reference' => $this->payment_hash->hash],
        ], $extra));

        $order = MerchantApi::body($response);

        if (! $response?->successful() || empty($order['id'])) {
            $this->failPayment(MerchantApi::errorMessage($order, 'Failed to create Revolut order'), $order);
        }

        $this->payment_hash->withData('order_id', $order['id']);
        $this->payment_hash->withData('gateway_type_id', $gateway_type_id);

        return $order;
    }

    /**
     * The order created on an earlier visit, as long as it can still be paid.
     * Avoids a new Revolut order every time the payment page is reloaded.
     */
    public function reusableOrder(int $gateway_type_id): ?array
    {
        $order_id = $this->payment_hash->data->order_id ?? null;

        if (! $order_id || (int) ($this->payment_hash->data->gateway_type_id ?? 0) !== $gateway_type_id) {
            return null;
        }

        $order = $this->api()->retrieveOrder($order_id);

        if ($order && $this->orderState($order) === 'pending'
            && ($order['amount'] ?? null) === $this->convertToRevolutAmount((float) $this->payment_hash->amount_with_fee())) {
            return $order;
        }

        return null;
    }

    /**
     * Re-read an in-flight order for a few seconds: automatic capture usually
     * completes shortly after the payment.
     */
    public function awaitOrder(array $order, int $attempts = self::POLL_ATTEMPTS): array
    {
        for ($i = 1; in_array($this->orderState($order), self::IN_FLIGHT_STATES, true) && $i < $attempts; $i++) {
            sleep(1);
            $order = $this->api()->retrieveOrder($order['id']) ?? $order;
        }

        return $order;
    }

    /**
     * The customer is back in the portal after paying: read the order back,
     * record it and show the payment.
     */
    public function completeCustomerPayment(int $attempts = self::POLL_ATTEMPTS): RedirectResponse
    {
        $order_id = $this->payment_hash->data->order_id ?? null;

        if (! $order_id) {
            $this->failPayment('Missing [order_id] on payment hash ' . $this->payment_hash->hash);
        }

        $order = $this->api()->retrieveOrder($order_id);

        if (! $order) {
            $this->failPayment('Could not retrieve the Revolut order ' . $order_id);
        }

        $order = $this->awaitOrder($order, $attempts);

        $payment = $this->recordOrder($order);

        if (! $payment) {
            $this->failPayment(
                $this->declineReason($order) ?? 'Payment not completed. Order state: ' . $this->orderState($order),
                $order,
            );
        }

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    /**
     * Record an order against the current payment hash. Safe to call
     * repeatedly and concurrently (return redirect, webhook):
     *
     *   completed             completed payment (or completes a pending one)
     *   processing/authorised pending payment, completed by a later webhook
     *   cancelled/failed      fails a pending payment
     *   pending               nothing to record
     *
     * Returns the payment, or null when the order is not paid.
     */
    public function recordOrder(array $order): ?Payment
    {
        $state = $this->orderState($order);

        $status = match (true) {
            $state === 'completed' => Payment::STATUS_COMPLETED,
            in_array($state, self::IN_FLIGHT_STATES, true) => Payment::STATUS_PENDING,
            default => null,
        };

        $expected_amount = $this->convertToRevolutAmount((float) $this->payment_hash->amount_with_fee());

        if ($status !== null && (($order['amount'] ?? null) !== $expected_amount
            || strtoupper((string) ($order['currency'] ?? '')) !== $this->client->getCurrencyCode())) {
            $this->log(['message' => 'Order amount does not match the payment hash', 'expected' => $expected_amount, 'order' => $order], false);

            throw new PaymentFailed('Revolut order amount does not match the invoice amount.');
        }

        // A cache lock, not a DB transaction: createPayment() fires listeners and,
        // with the sync queue, jobs that write on other connections and would
        // wait on our own row locks.
        try {
            return Cache::lock('revolut_order_' . $order['id'], 60)
                ->block(15, fn () => $this->recordOrderWithinLock($order, $state, $status));
        } catch (LockTimeoutException) {
            $payment = $this->existingPayment((string) $order['id']);

            if ($payment) {
                return $payment->status_id === Payment::STATUS_FAILED ? null : $payment;
            }

            throw new PaymentFailed('This Revolut payment is already being processed. Please try again shortly.', 409);
        }
    }

    private function recordOrderWithinLock(array $order, string $state, ?int $status): ?Payment
    {
        $payment = $this->existingPayment((string) $order['id']);

        if ($payment) {
            return $this->transition($payment, $state, $order);
        }

        if ($status === null) {
            return null;
        }

        $gateway_type_id = (int) ($this->payment_hash->data->gateway_type_id ?? GatewayType::HOSTED_PAGE);

        $data = [
            'gateway_type_id' => $gateway_type_id,
            'amount' => $this->payment_hash->amount_with_fee(),
            'payment_type' => $this->paymentType($gateway_type_id, $order),
            'transaction_reference' => (string) $order['id'],
            'idempotency_key' => substr($order['id'] . '_' . $this->payment_hash->hash, 0, 64),
        ];

        $payment = $this->createPayment($data, $status);

        $this->log(['response' => $order, 'data' => $data], true);

        return $payment;
    }

    /**
     * Move an existing payment along when the order reached a final state.
     */
    private function transition(Payment $payment, string $state, array $order): ?Payment
    {
        if ($payment->status_id !== Payment::STATUS_PENDING) {
            return $payment->status_id === Payment::STATUS_FAILED ? null : $payment;
        }

        if ($state === 'completed') {
            $payment = $payment->service()->completePendingPayment();
            $this->log(['message' => 'Pending payment completed', 'payment_id' => $payment->id, 'response' => $order], true);

            return $payment;
        }

        if (in_array($state, ['cancelled', 'failed'], true)) {
            $payment->service()->deletePayment();
            $payment->status_id = Payment::STATUS_FAILED;
            $payment->save();
            $this->log(['message' => "Pending payment failed: {$state}", 'payment_id' => $payment->id, 'response' => $order], false);

            return null;
        }

        return $payment;
    }

    private function existingPayment(string $order_id): ?Payment
    {
        return Payment::query()
            ->where('transaction_reference', $order_id)
            ->where('client_id', $this->client->id)
            ->first();
    }

    private function paymentType(int $gateway_type_id, array $order): int
    {
        return match ($gateway_type_id) {
            GatewayType::HOSTED_PAGE => PaymentType::HOSTED_PAGE,
            default => PaymentType::CREDIT_CARD_OTHER,
        };
    }

    public function orderState(array $order): string
    {
        return strtolower((string) ($order['state'] ?? ''));
    }

    /**
     * The decline reason of the latest payment attempt, if any.
     */
    public function declineReason(array $order): ?string
    {
        $payment = collect($order['payments'] ?? [])->last();

        $reason = $payment['decline_reason'] ?? null;

        return $reason ? str_replace('_', ' ', ucfirst((string) $reason)) : null;
    }

    /**
     * Mail the merchant, log, and stop the payment.
     */
    public function failPayment(string $message, array $response = []): never
    {
        $this->sendFailureMail($message);

        $this->log(['message' => $message, 'response' => $response], false);

        throw new PaymentFailed($message);
    }

    public function log(array $data, bool $success): void
    {
        SystemLogger::dispatch(
            $data,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            $success ? SystemLog::EVENT_GATEWAY_SUCCESS : SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_REVOLUT,
            $this->client,
            $this->client->company,
        );
    }

    /**
     * Revolut amounts are integers in the currency's minor unit.
     */
    public function convertToRevolutAmount(float $amount): int
    {
        return (int) round($amount * pow(10, $this->currencyPrecision()));
    }

    public function convertFromRevolutAmount(int|float $amount): float
    {
        return round($amount / pow(10, $this->currencyPrecision()), $this->currencyPrecision());
    }

    private function currencyPrecision(): int
    {
        return (int) ($this->client->currency()->precision ?? 2);
    }
}
