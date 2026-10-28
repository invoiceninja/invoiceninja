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
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\DeluxePaymentPlatform\ACH;
use App\PaymentDrivers\DeluxePaymentPlatform\CreditCard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Deluxe Payment Platform (https://developer.deluxe.com).
 *
 * Uses the Deluxe hosted Payment Link flow: the client is redirected to a
 * Deluxe-hosted page and the payment is recorded when Deluxe posts a
 * TRANSACTION webhook to /payment_webhook/{company_key}/{company_gateway_id}.
 */
class DeluxePaymentPlatformPaymentDriver extends BaseDriver
{
    public $refundable = true;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_DELUXE;

    private const SANDBOX_API_URL = 'https://sandbox.api.deluxe.com/dpp/v1/';

    private const PRODUCTION_API_URL = 'https://api.deluxe.com/dpp/v1/';

    private const SANDBOX_TOKEN_URL = 'https://sandbox.api.deluxe.com/secservices/oauth2/v2/token';

    private const PRODUCTION_TOKEN_URL = 'https://api.deluxe.com/secservices/oauth2/v2/token';

    /**
     * Deluxe bearer tokens expire after 55 minutes; cache them for 50.
     */
    private const TOKEN_TTL = 3000;

    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
            GatewayType::BANK_TRANSFER,
        ];
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    public function isTestMode(): bool
    {
        return (bool) $this->company_gateway->getConfigField('testMode');
    }

    public function getApiUrl(): string
    {
        return $this->isTestMode() ? self::SANDBOX_API_URL : self::PRODUCTION_API_URL;
    }

    private function getTokenUrl(): string
    {
        return $this->isTestMode() ? self::SANDBOX_TOKEN_URL : self::PRODUCTION_TOKEN_URL;
    }

    private function config(string $field): string
    {
        return trim((string) $this->company_gateway->getConfigField($field));
    }

    /**
     * OAuth2 client-credentials bearer token, cached per gateway and credential set.
     */
    public function getBearerToken(): string
    {
        $cache_key = 'deluxe_bearer_'.$this->company_gateway->id.'_'.md5($this->getTokenUrl().$this->config('clientId').$this->config('clientSecret'));

        return Cache::remember($cache_key, self::TOKEN_TTL, fn () => $this->fetchBearerToken());
    }

    private function fetchBearerToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->config('clientId'), $this->config('clientSecret'))
            ->acceptJson()
            ->timeout(15)
            ->post($this->getTokenUrl(), ['grant_type' => 'client_credentials']);

        $token = $response->json('access_token');

        if (! $response->successful() || empty($token)) {
            throw new PaymentFailed('Deluxe authentication failed. Check the Client ID and Client Secret.', 401);
        }

        return $token;
    }

    /**
     * Make an authenticated request to the Deluxe Payment Platform API.
     */
    public function gatewayRequest(string $method, string $endpoint, array $payload = []): \Illuminate\Http\Client\Response
    {
        $request = Http::withToken($this->getBearerToken())
            ->withHeaders([
                'partnerToken' => $this->config('partnerToken'),
                'requestId' => (string) Str::uuid(),
            ])
            ->acceptJson()
            ->timeout(30);

        $url = $this->getApiUrl().ltrim($endpoint, '/');

        return strtoupper($method) === 'GET'
            ? $request->get($url, $payload)
            : $request->send(strtoupper($method), $url, ['json' => $payload]);
    }

    /**
     * Create a Deluxe hosted payment link for the current payment hash.
     *
     * @param  array  $accept_payment_method  ['Card'] or ['ACH']
     */
    public function createPaymentLink(array $accept_payment_method): string
    {
        $invoice_numbers = collect($this->payment_hash->invoices())->pluck('invoice_number')->filter()->implode(', ');

        $payload = [
            'amount' => [
                'amount' => round((float) $this->payment_hash->data->amount_with_fee, 2),
                'currency' => $this->client->currency()->code,
            ],
            'acceptPaymentMethod' => $accept_payment_method,
            'deliveryMethod' => [
                'type' => 'None',
                'email' => '',
                'sms' => '',
            ],
            'paymentLinkExpiry' => '1 DAYS',
            'firstName' => $this->client->present()->first_name() ?: $this->client->present()->name(),
            'lastName' => $this->client->present()->last_name() ?: '-',
            'orderData' => [
                'orderId' => $this->payment_hash->hash,
                'description' => $invoice_numbers !== '' ? ctrans('texts.invoice').' '.$invoice_numbers : ctrans('texts.payment'),
            ],
            'customData' => [
                ['name' => 'PaymentHash', 'value' => $this->payment_hash->hash],
            ],
        ];

        $response = $this->gatewayRequest('POST', 'paymentlinks', $payload);

        $url = $response->json('paymentLinkURL') ?? $response->json('paymentLinkUrl');

        if (! $response->successful() || empty($url)) {
            $message = $response->json('errors.message') ?? $response->json('message') ?? 'Unable to create Deluxe payment link';

            SystemLogger::dispatch(
                ['response' => $response->json() ?? $response->body()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_DELUXE,
                $this->client,
                $this->client->company,
            );

            throw new PaymentFailed(is_string($message) ? $message : json_encode($message), 400);
        }

        return $url;
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

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $response = $this->gatewayRequest('POST', 'refunds', [
            'paymentId' => $payment->transaction_reference,
            'amount' => [
                'amount' => round((float) $amount, 2),
                'currency' => $payment->currency->code ?? $payment->client->currency()->code,
            ],
        ]);

        $success = $response->successful() && ! empty($response->json('paymentId'));

        SystemLogger::dispatch(
            ['server_response' => $response->json() ?? $response->body()],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            $success ? SystemLog::EVENT_GATEWAY_SUCCESS : SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_DELUXE,
            $payment->client,
            $payment->company,
        );

        return [
            'transaction_reference' => $response->json('paymentId') ?? $payment->transaction_reference,
            'success' => $success,
            'description' => $success ? ctrans('texts.refunded') : ($response->json('errors.message') ?? $response->json('message') ?? 'Refund failed'),
            'code' => $response->status(),
        ];
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        // Deluxe Payment Links do not create reusable tokens.
    }

    /**
     * Validate the credentials. Called by the "Check Credentials" button in the admin UI.
     */
    public function auth(): string
    {
        if ($this->config('clientId') === '' || $this->config('clientSecret') === '' || $this->config('partnerToken') === '') {
            return 'error';
        }

        try {
            $this->fetchBearerToken();

            return 'ok';
        } catch (\Throwable $e) {
            return $e->getMessage() ?: 'error';
        }
    }

    /**
     * Process a Deluxe webhook.
     *
     * Deluxe does not sign webhook requests, so the URL subscribed in the Deluxe
     * merchant portal must carry the gateway's webhook secret:
     * /payment_webhook/{company_key}/{company_gateway_id}?secret={webhookSecret}
     */
    public function processWebhookRequest($request)
    {
        $secret = $this->config('webhookSecret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook secret is not configured'], 401);
        }

        if (! hash_equals($secret, (string) $request->query('secret', ''))) {
            return response()->json(['message' => 'Invalid webhook secret'], 401);
        }

        // Deluxe sends PascalCase keys; normalise them so lookups are case-insensitive.
        $payload = $this->lowercaseKeys($request->json()->all());

        $event_type = strtoupper((string) ($payload['eventtype'] ?? ''));

        return match ($event_type) {
            'TRANSACTION' => $this->processTransactionWebhook($payload),
            'ACH REJECT', 'ACH_REJECT', 'ACHREJECT' => $this->processAchRejectWebhook($payload),
            default => response()->json(['message' => 'Event ignored'], 200),
        };
    }

    private function processTransactionWebhook(array $payload)
    {
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if (! in_array($status, ['APPROVED', 'PENDING', 'SETTLED'], true)) {
            return response()->json(['message' => 'Status ignored'], 200);
        }

        $transaction_reference = $payload['transactionid'] ?? $payload['paymentid'] ?? null;
        $hash = $this->findPaymentHash($payload);

        if (! $hash || ! $transaction_reference) {
            return response()->json(['message' => 'Payment hash not found'], 200);
        }

        $invoice = Invoice::withTrashed()
            ->where('company_id', $this->company_gateway->company_id)
            ->find($hash->fee_invoice_id);

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 200);
        }

        $this->setClient($invoice->client);
        $this->setPaymentHash($hash);

        $is_ach = strtoupper((string) ($payload['paymenttype'] ?? '')) === 'ACH';
        $is_final = in_array($status, ['APPROVED', 'SETTLED'], true);

        $expected_amount = round((float) $hash->data->amount_with_fee, 2);
        $paid_amount = round((float) ($payload['transactionamount'] ?? $payload['amount'] ?? 0), 2);

        // Never record more than Deluxe actually collected.
        if ($paid_amount + 0.005 < $expected_amount) {
            $this->logWebhook($payload, SystemLog::EVENT_GATEWAY_FAILURE, "Amount mismatch: expected {$expected_amount}, received {$paid_amount}");

            return response()->json(['message' => 'Amount mismatch'], 200);
        }

        return DB::transaction(function () use ($hash, $payload, $transaction_reference, $is_ach, $is_final) {
            /** @var PaymentHash $locked_hash */
            $locked_hash = PaymentHash::where('id', $hash->id)->lockForUpdate()->first();

            // ACH sends PENDING first, then APPROVED/SETTLED for the same payment.
            if ($locked_hash->payment_id) {
                $payment = Payment::where('id', $locked_hash->payment_id)
                    ->where('company_gateway_id', $this->company_gateway->id)
                    ->first();

                if ($payment && $is_final && $payment->status_id === Payment::STATUS_PENDING && ! $payment->is_deleted) {
                    $payment->service()->completePendingPayment();
                }

                return response()->json(['message' => 'Webhook processed'], 200);
            }

            $this->setPaymentHash($locked_hash);

            $this->createPayment([
                'amount' => $locked_hash->data->amount_with_fee,
                'payment_type' => $is_ach ? PaymentType::ACH : PaymentType::CREDIT_CARD_OTHER,
                'gateway_type_id' => $is_ach ? GatewayType::BANK_TRANSFER : GatewayType::CREDIT_CARD,
                'transaction_reference' => $transaction_reference,
            ], (! $is_ach || $is_final) ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING);

            $this->logWebhook($payload, SystemLog::EVENT_GATEWAY_SUCCESS);

            return response()->json(['message' => 'Webhook processed'], 200);
        });
    }

    /**
     * The bank rejected an ACH debit: reverse the pending payment and notify the company.
     */
    private function processAchRejectWebhook(array $payload)
    {
        $transaction_reference = $payload['transactionid'] ?? $payload['paymentid'] ?? null;

        if (! $transaction_reference) {
            return response()->json(['message' => 'Transaction not found'], 200);
        }

        return DB::transaction(function () use ($payload, $transaction_reference) {
            /** @var Payment|null $payment */
            $payment = Payment::where('company_id', $this->company_gateway->company_id)
                ->where('company_gateway_id', $this->company_gateway->id)
                ->where('transaction_reference', $transaction_reference)
                ->where('status_id', Payment::STATUS_PENDING)
                ->where('is_deleted', false)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return response()->json(['message' => 'Payment not found'], 200);
            }

            $client = $payment->client;
            $payment_hash = PaymentHash::where('payment_id', $payment->id)->first();

            $payment->service()->deletePayment();
            $payment->status_id = Payment::STATUS_FAILED;
            $payment->save();

            $this->setClient($client);
            $this->logWebhook($payload, SystemLog::EVENT_GATEWAY_FAILURE, 'ACH payment rejected by the bank');

            PaymentFailedMailer::dispatch(
                $payment_hash,
                $client->company,
                $client,
                "Deluxe ACH payment {$transaction_reference} was rejected by the bank."
            );

            return response()->json(['message' => 'Webhook processed'], 200);
        });
    }

    /**
     * The payment hash is sent as customData when the payment link is created
     * and echoed back by Deluxe as customFields (or customData).
     */
    private function findPaymentHash(array $payload): ?PaymentHash
    {
        $hash = null;

        foreach (($payload['customfields'] ?? $payload['customdata'] ?? []) as $field) {
            if (is_array($field) && strtolower((string) ($field['name'] ?? '')) === 'paymenthash') {
                $hash = (string) ($field['value'] ?? '');
                break;
            }
        }

        $hash = $hash ?: ($payload['orderid'] ?? $payload['invoicenumber'] ?? null);

        if (! $hash) {
            return null;
        }

        return PaymentHash::where('hash', $hash)->first();
    }

    private function lowercaseKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalised = [];

        foreach ($value as $key => $item) {
            $normalised[is_string($key) ? strtolower($key) : $key] = $this->lowercaseKeys($item);
        }

        return $normalised;
    }

    private function logWebhook(array $payload, int $event, ?string $message = null): void
    {
        SystemLogger::dispatch(
            array_filter(['webhook_payload' => $payload, 'message' => $message]),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            $event,
            SystemLog::TYPE_DELUXE,
            $this->client,
            $this->client->company,
        );
    }
}
