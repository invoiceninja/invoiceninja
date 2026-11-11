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
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Revolut\CreditCard;
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

    /** How long (seconds) a charge of a saved method is re-read: it settles more slowly. */
    public const SAVED_METHOD_POLL_ATTEMPTS = 15;

    /** Payment states of a successful payment within an order. */
    private const SUCCESSFUL_PAYMENT_STATES = ['authorisation_passed', 'authorised', 'capture_started', 'captured', 'completing', 'completed'];

    /** Payment states of a failed attempt, after which the order can be paid again. */
    private const FAILED_PAYMENT_STATES = ['declined', 'soft_declined', 'cancelled', 'failed'];

    /** saved_payment_method.type per gateway type. */
    private const SAVED_METHOD_TYPES = [
        GatewayType::CREDIT_CARD => 'card',
    ];

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = false;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
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
            GatewayType::CREDIT_CARD,
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
     * Charge a saved payment method with no customer present (auto-bill): a
     * merchant-initiated payment on a new order for the method's customer.
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->setPaymentHash($payment_hash);

        if ((int) $cgt->client_id !== (int) $this->client->id
            || (int) $cgt->company_gateway_id !== (int) $this->company_gateway->id
            || empty($cgt->gateway_customer_reference)) {
            $this->failPayment('This saved payment method cannot be charged by this gateway.');
        }

        $order = $this->createOrder((int) $cgt->gateway_type_id, [
            'customer' => ['id' => $cgt->gateway_customer_reference],
        ]);

        $this->chargeSavedMethod($order['id'], $cgt);

        $order = $this->awaitOrder($this->api()->retrieveOrder($order['id']) ?? $order, self::SAVED_METHOD_POLL_ATTEMPTS);

        $payment = $this->recordOrder($order);

        if (! $payment) {
            $this->failPayment(
                $this->declineReason($order) ?? 'Payment not completed. Order state: ' . $this->orderState($order),
                $order,
            );
        }

        return $payment;
    }

    /**
     * The client pays in the portal with a method they saved earlier. Like
     * auto-billing it is a merchant-initiated charge of the saved method, so
     * no 3DS step is needed. The page's order is moved to the method's
     * Revolut customer and paid.
     *
     * @see https://developer.revolut.com/docs/api/merchant#pay-for-an-order
     */
    public function payWithSavedMethod(ClientGatewayToken $cgt): RedirectResponse
    {
        // A payment hash is charged once, however often the page is posted
        // (double submits, retried requests): the charge is made under a
        // lock, and an order that was already charged is only recorded.
        try {
            Cache::lock('revolut_charge_' . $this->payment_hash->hash, 60)
                ->block(20, fn () => $this->chargeSavedMethodOnce($cgt));
        } catch (LockTimeoutException) {
            // Another request is charging this payment: record what it charged.
        }

        // The order may have been replaced by whichever request held the lock.
        $this->payment_hash->refresh();

        return $this->completeCustomerPayment(self::SAVED_METHOD_POLL_ATTEMPTS);
    }

    /**
     * Charge the saved method on the payment hash's order, unless that order
     * was already charged.
     */
    private function chargeSavedMethodOnce(ClientGatewayToken $cgt): void
    {
        // Another request may have replaced the order while this one waited for the lock.
        $this->payment_hash->refresh();

        $gateway_type_id = (int) $cgt->gateway_type_id;
        $order_id = $this->payment_hash->data->order_id ?? null;
        $order = $order_id ? $this->api()->retrieveOrder($order_id) : null;

        if ($order_id && ! $order) {
            // Without the order's state we can't tell whether it was already
            // charged, so never charge again: the client can retry, and the
            // order's webhook still records it.
            $this->log(['message' => 'Could not retrieve the Revolut order before charging a saved method', 'order_id' => $order_id], false);

            throw new PaymentFailed(ctrans('texts.payment_error'));
        }

        if ($order && $this->orderWasCharged($order)) {
            return;
        }

        if ($order && in_array($this->orderState($order), ['cancelled', 'failed'], true)) {
            // A replaced order's webhooks are ignored, so settle it now.
            $this->recordOrder($order);
        }

        $customer = ['customer' => ['id' => $cgt->gateway_customer_reference]];

        // Reuse the order the page was mounted on; a new one only if it can no longer be paid.
        if (! $order || ! $this->canReuseOrder($order, $gateway_type_id) || ! $this->api()->updateOrder($order['id'], $customer)) {
            $order = $this->createOrder($gateway_type_id, $customer);
        }

        $this->chargeSavedMethod($order['id'], $cgt);
    }

    /**
     * Whether an order already has a payment that succeeded or is still in
     * progress. Only failed attempts (a declined card) leave it chargeable.
     */
    private function orderWasCharged(array $order): bool
    {
        $state = $this->orderState($order);

        if ($state === 'pending') {
            return collect($order['payments'] ?? [])
                ->contains(fn ($p) => ! in_array(strtolower((string) ($p['state'] ?? '')), self::FAILED_PAYMENT_STATES, true));
        }

        return ! in_array($state, ['cancelled', 'failed'], true);
    }

    /**
     * The payment page posted back: either the client chose a saved method
     * (charged now), or the card field / Revolut Pay already paid the order.
     */
    public function completePortalPayment(PaymentResponseRequest $request, int $gateway_type_id): RedirectResponse
    {
        if (! $request->shouldUseToken()) {
            return $this->completeCustomerPayment();
        }

        $cgt = $this->savedPaymentMethod((string) $request->input('token'), $gateway_type_id);

        if (! $cgt) {
            $this->failPayment(ctrans('texts.payment_token_not_found'));
        }

        return $this->payWithSavedMethod($cgt);
    }

    /**
     * Pay an order with a saved method as a merchant-initiated payment.
     * Stops the payment (mail, log, throw) when Revolut refuses it.
     */
    private function chargeSavedMethod(string $order_id, ClientGatewayToken $cgt): void
    {
        $response = $this->api()->payOrder($order_id, [
            'type' => self::SAVED_METHOD_TYPES[(int) $cgt->gateway_type_id] ?? 'card',
            'id' => $cgt->token,
            'initiator' => 'merchant',
        ]);

        if (! $response?->successful()) {
            $body = MerchantApi::body($response);

            $this->failPayment(MerchantApi::errorMessage($body, 'Revolut declined the charge'), $body);
        }
    }

    /**
     * A saved method of this client on this gateway, from its hashed id.
     */
    public function savedPaymentMethod(?string $hashed_id, int $gateway_type_id): ?ClientGatewayToken
    {
        if (! $hashed_id) {
            return null;
        }

        return ClientGatewayToken::query()
            ->where('id', $this->decodePrimaryKey($hashed_id))
            ->where('client_id', $this->client->id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->where('gateway_type_id', $gateway_type_id)
            ->where('is_deleted', 0)
            ->whereNotNull('gateway_customer_reference')
            ->first();
    }

    /**
     * A zero-amount order on the client's Revolut customer, used to save a
     * card from the portal's "add payment method" page without charging it.
     */
    public function createSetupOrder(string $customer_id): array
    {
        $response = $this->api()->createOrder([
            'amount' => 0,
            'currency' => $this->client->getCurrencyCode(),
            'capture_mode' => 'automatic',
            'customer' => ['id' => $customer_id],
            'description' => ctrans('texts.add_payment_method'),
        ]);

        $order = MerchantApi::body($response);

        if (! $response?->successful() || empty($order['id']) || empty($order['token'])) {
            $this->log(['message' => 'Could not create Revolut setup order', 'response' => $order], false);

            throw new PaymentFailed(MerchantApi::errorMessage($order, 'Failed to create Revolut order'));
        }

        return $order;
    }

    /**
     * Forget the saved method at Revolut when the client removes it.
     */
    public function detach(ClientGatewayToken $token)
    {
        if ($token->gateway_customer_reference && ! $this->api()->deleteSavedPaymentMethod($token->gateway_customer_reference, $token->token)) {
            nlog('Revolut: could not delete saved payment method ' . $token->token);
        }

        return true;
    }

    /**
     * Whether the merchant lets clients save payment methods on this gateway.
     */
    public function canSavePaymentMethods(): bool
    {
        return ($this->company_gateway->token_billing ?? 'off') !== 'off';
    }

    /**
     * The Revolut customer that saved methods belong to: the one already on
     * the client's saved methods, else a new one. Revolut needs the customer
     * on the order for the payment method to be saved.
     */
    public function revolutCustomerId(): ?string
    {
        $customer_id = ClientGatewayToken::query()
            ->where('client_id', $this->client->id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->whereNotNull('gateway_customer_reference')
            ->value('gateway_customer_reference');

        if ($customer_id) {
            return $customer_id;
        }

        $contact = $this->getContact() ?: $this->client->primary_contact()->first() ?: $this->client->contacts()->first();

        if (! $contact || ! $contact->email) {
            return null;
        }

        return $this->api()->createCustomer(array_filter([
            'full_name' => trim($contact->first_name . ' ' . $contact->last_name) ?: $this->client->present()->name(),
            'email' => $contact->email,
        ]));
    }

    /**
     * Keep the payment method of the order's successful payment, once
     * Revolut confirms it is saved on the customer for merchant use.
     *
     * Revolut saves a new payment method each time a card is entered, so a
     * card the client already saved is recognised by its fingerprint: the
     * existing saved card is kept and the duplicate is removed at Revolut.
     */
    public function storeSavedPaymentMethod(array $order, int $gateway_type_id): ?ClientGatewayToken
    {
        $customer_id = $order['customer']['id'] ?? null;
        $method = $this->successfulPaymentMethod($order);

        if (! $customer_id || empty($method['id'])) {
            return null;
        }

        $saved = ClientGatewayToken::query()
            ->where('client_id', $this->client->id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->where('gateway_type_id', $gateway_type_id)
            ->where('is_deleted', 0)
            ->get();

        $existing = $saved->first(fn (ClientGatewayToken $cgt) => $cgt->token === $method['id']);

        if ($existing) {
            return $existing;
        }

        $fingerprint = $method['fingerprint'] ?? null;
        $same_card = $fingerprint ? $saved->first(fn (ClientGatewayToken $cgt) => ($cgt->meta->fingerprint ?? null) === $fingerprint) : null;

        if ($same_card) {
            $this->api()->deleteSavedPaymentMethod($customer_id, $method['id']);

            return $same_card;
        }

        $saved_for_merchant = collect($this->api()->savedPaymentMethods($customer_id))
            ->contains(fn ($m) => ($m['id'] ?? null) === $method['id'] && strtolower((string) ($m['saved_for'] ?? '')) === 'merchant');

        if (! $saved_for_merchant) {
            return null;
        }

        return $this->storeGatewayToken([
            'token' => $method['id'],
            'payment_method_id' => $gateway_type_id,
            'payment_meta' => $this->paymentMeta($method, $gateway_type_id),
        ], ['gateway_customer_reference' => $customer_id]);
    }

    /**
     * The payment method of the order's successful payment. A declined
     * attempt can come first when the customer retried on the same order.
     */
    private function successfulPaymentMethod(array $order): ?array
    {
        $payment = collect($order['payments'] ?? [])
            ->first(fn ($p) => in_array(strtolower((string) ($p['state'] ?? '')), self::SUCCESSFUL_PAYMENT_STATES, true));

        return $payment['payment_method'] ?? null;
    }

    private function paymentMeta(array $method, int $gateway_type_id): \stdClass
    {
        $meta = new \stdClass();
        $meta->type = $gateway_type_id;
        $meta->brand = ucfirst(strtolower((string) ($method['card_brand'] ?? '')));
        $meta->last4 = (string) ($method['card_last_four'] ?? '');

        [$month, $year] = array_pad(explode('/', (string) ($method['card_expiry'] ?? '')), 2, '');
        $meta->exp_month = $month;
        $meta->exp_year = strlen($year) === 2 ? '20' . $year : $year;
        $meta->fingerprint = $method['fingerprint'] ?? null;

        return $meta;
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
        $order = $this->reusableOrder($gateway_type_id);

        if ($order) {
            return $order;
        }

        // Saving a method needs the Revolut customer on the order.
        if ($gateway_type_id !== GatewayType::HOSTED_PAGE && $this->canSavePaymentMethods() && ($customer_id = $this->revolutCustomerId())) {
            $extra['customer'] = ['id' => $customer_id];
        }

        return $this->createOrder($gateway_type_id, $extra);
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

        return $order && $this->canReuseOrder($order, $gateway_type_id) ? $order : null;
    }

    /**
     * An order can be paid again while it is still pending for the same
     * payment type and amount.
     */
    private function canReuseOrder(array $order, int $gateway_type_id): bool
    {
        return (int) ($this->payment_hash->data->gateway_type_id ?? 0) === $gateway_type_id
            && $this->orderState($order) === 'pending'
            && ($order['amount'] ?? null) === $this->convertToRevolutAmount((float) $this->payment_hash->amount_with_fee());
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
     * record it, and show the payment.
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
                ->block(15, function () use ($order, $state, $status) {
                    $payment = $this->recordOrderWithinLock($order, $state, $status);

                    if ($payment) {
                        $this->keepSavedPaymentMethod($order);
                    }

                    return $payment;
                });
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
     * Keep the payment method the client chose to save. Revolut only saves it
     * for the merchant when the client asked to, so this runs every time a
     * paid order is recorded (the return to the portal and the webhook, in
     * either order), under the order's lock so it is stored once.
     */
    private function keepSavedPaymentMethod(array $order): void
    {
        $gateway_type_id = (int) ($this->payment_hash->data->gateway_type_id ?? 0);

        if (array_key_exists($gateway_type_id, self::SAVED_METHOD_TYPES) && $this->canSavePaymentMethods()) {
            $this->storeSavedPaymentMethod($order, $gateway_type_id);
        }
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
            default => match (strtolower((string) ($this->successfulPaymentMethod($order)['card_brand'] ?? ''))) {
                'visa' => PaymentType::VISA,
                'mastercard' => PaymentType::MASTERCARD,
                'american_express', 'amex' => PaymentType::AMERICAN_EXPRESS,
                'maestro' => PaymentType::MAESTRO,
                'discover' => PaymentType::DISCOVER,
                'diners', 'diners_club' => PaymentType::DINERS,
                'jcb' => PaymentType::JCB,
                'unionpay' => PaymentType::UNIONPAY,
                default => PaymentType::CREDIT_CARD_OTHER,
            },
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
