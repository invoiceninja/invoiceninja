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
use App\Jobs\Mail\PaymentFailedMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\GoCardless\HostedPaymentPage;
use App\PaymentDrivers\GoCardless\Jobs\GoCardlessWebhook;
use App\PaymentDrivers\GoCardless\Jobs\ImportGoCardlessCustomers;
use App\Utils\BcMath;
use App\Utils\Traits\MakesHash;

class GoCardlessPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public \GoCardlessPro\Client $gateway;

    public $payment_method;

    public static $methods = [
        GatewayType::BANK_TRANSFER => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::DIRECT_DEBIT => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::SEPA => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::INSTANT_BANK_PAY => \App\PaymentDrivers\GoCardless\InstantBankPay::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_GOCARDLESS;

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function ensurePaymentMethodAvailable(int $gateway_type_id, float $amount = -1): self
    {
        $is_available = collect($this->client->service()->getPaymentMethods($amount))
            ->contains(fn(array $method): bool
                => (int) $method['company_gateway_id'] === (int) $this->company_gateway->id
                && (int) $method['gateway_type_id'] === $gateway_type_id);

        if (! $is_available) {
            throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'), 403);
        }

        return $this;
    }

    public function resolveClientGatewayToken(string $mandate_id, int $gateway_type_id): ClientGatewayToken
    {
        $token = $this->resolveOwnedClientGatewayToken($mandate_id);

        if ((int) $token->gateway_type_id !== $gateway_type_id) {
            throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'), 403);
        }

        return $token;
    }

    public function resolveOwnedClientGatewayToken(string $mandate_id): ClientGatewayToken
    {
        $token = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->company_gateway->id)
            ->where('client_id', $this->client->id)
            ->where('token', $mandate_id)
            ->first();

        if (! $token) {
            throw new PaymentFailed(ctrans('texts.gateway_temporarily_unavailable'), 403);
        }

        return $token;
    }

    public function gatewayTypes(): array
    {
        if (! $this->client || ! isset($this->client->country)) {
            return [];
        }

        $types = [];
        $country = $this->client->country->iso_3166_2;
        $currency = $this->client->currency()->code;

        if ($country === 'US' && $currency === 'USD') {
            $types[] = GatewayType::BANK_TRANSFER;
        }

        $direct_debit_countries = [
            'AUD' => 'AU',
            'CAD' => 'CA',
            'DKK' => 'DK',
            'GBP' => 'GB',
            'NZD' => 'NZ',
            'SEK' => 'SE',
        ];
        $supports_sepa = $currency === 'EUR' && in_array($country, HostedPaymentPage::SEPA_COUNTRIES, true);

        if (($direct_debit_countries[$currency] ?? null) === $country) {
            $types[] = GatewayType::DIRECT_DEBIT;
        }

        if ($supports_sepa) {
            $types[] = GatewayType::SEPA;
        }

        if (($currency === 'GBP' && $country === 'GB')
            || ($currency === 'EUR' && in_array($country, ['IE', 'FR', 'DE'], true))) {
            $types[] = GatewayType::INSTANT_BANK_PAY;
        }

        return $types;
    }

    public function init(): self
    {
        $environment = $this->company_gateway->getConfigField('testMode') ? \GoCardlessPro\Environment::SANDBOX : \GoCardlessPro\Environment::LIVE;

        if ($this->company_gateway->getConfigField('oauth2')) {
            $environment = \GoCardlessPro\Environment::LIVE;
        }

        try {
            $this->gateway = new \GoCardlessPro\Client([
                'access_token' => $this->company_gateway->getConfigField('accessToken'),
                'environment'  => $environment,
            ]);
        } catch (\GoCardlessPro\Core\Exception\AuthenticationException $e) {

            throw new \Exception('GoCardless: Invalid Access Token', 403);

        }

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

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        // ..
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $cgt = $this->resolveOwnedClientGatewayToken($cgt->token);
        $converted_amount = $this->convertToGoCardlessAmount($amount, $this->client->currency()->precision);

        $this->init();

        if ($payment_hash->fee_invoice) {
            $description = "Invoice {$payment_hash->fee_invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Amount {$amount} from client {$this->client->present()->name()}";
        }

        try {
            $payment = $this->gateway->payments()->create([
                'params' => [
                    'amount' => $converted_amount,
                    'currency' => $this->client->getCurrencyCode(),
                    'description' => $description,
                    'metadata' => [
                        'payment_hash' => $payment_hash->hash,
                    ],
                    'links' => [
                        'mandate' => $cgt->token,
                    ],
                ],
            ]);

            if (in_array($payment->status, ['submitted', 'pending_submission', 'confirmed', 'paid_out'], true)) {

                $data = [
                    'payment_method' => $cgt->hashed_id,
                    'payment_type' => HostedPaymentPage::paymentTypeForStoredMandate(
                        $cgt->gateway_type_id,
                        $this->client->getCurrencyCode(),
                        data_get($cgt->meta, 'scheme'),
                    ),
                    'amount' => $amount,
                    'transaction_reference' => $payment->id,
                    'gateway_type_id' => $cgt->gateway_type_id,
                ];

                $this->confirmGatewayFee($data);

                $status = in_array($payment->status, ['confirmed', 'paid_out'], true)
                    ? Payment::STATUS_COMPLETED
                    : Payment::STATUS_PENDING;
                $_payment = $this->createPayment($data, $status);

                SystemLogger::dispatch(
                    ['response' => $payment, 'data' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_GOCARDLESS,
                    $this->client,
                    $this->client->company
                );

                return $_payment;
            }

            $this->sendFailureMail($payment->status);

            $message = [
                'server_response' => $payment,
                'data' => $payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GOCARDLESS,
                $this->client,
                $this->client->company
            );

            return false;
        } catch (\Exception $exception) {

            $data = [
                'status' => '',
                'error_type' => '',
                'error_code' => $exception->getCode(),
                'param' => '',
                'message' => $exception->getMessage(),
            ];

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_GOCARDLESS, $this->client, $this->client->company);
        }
    }

    public function convertToGoCardlessAmount($amount, $precision): int
    {
        return BcMath::toMinorUnits($amount, (int) $precision);
    }

    public function detach(ClientGatewayToken $token)
    {
        $this->init();

        try {
            $this->gateway->mandates()->cancel($token->token);
        } catch (\Exception $e) {
            nlog($e->getMessage());

            SystemLogger::dispatch(
                [
                    'server_response' => $e->getMessage(),
                    'data' => request()->all(),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GOCARDLESS,
                $this->client,
                $this->client->company
            );
        }
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        // Allow app to catch up with webhook request.
        $this->init();

        nlog('GoCardless Event');

        $webhook_secret = $this->company_gateway->getConfigField('webhookSecret');

        if ($webhook_secret) {
            $sig_header = $request->header('Webhook-Signature');

            if (! $sig_header) {
                return response()->json(['error' => 'No signature header'], 403);
            }

            try {
                \GoCardlessPro\Webhook::parse(
                    $request->getContent(),
                    $sig_header,
                    $webhook_secret
                );
            } catch (\GoCardlessPro\Core\Exception\InvalidSignatureException $e) {
                nlog('GoCardless webhook signature verification failed: ' . $e->getMessage());

                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        if (! $request->has('events')) {
            nlog('No GoCardless events to process in response?');

            return response()->json([], 200);
        }

        GoCardlessWebhook::dispatch($request->events, $request->company_key, $this->decodePrimaryKey($request->company_gateway_id))->delay(2);

        return response()->json([], 200);
    }


    public function ensureMandateIsReady($token)
    {
        try {
            $this->init();
            $mandate = $this->gateway->mandates()->get($token);

            if (!in_array($mandate->status, ['pending_submission', 'submitted', 'active','pending_customer_approval'])) {

                // if ($mandate->status !== 'active') {
                throw new \Exception(ctrans('texts.gocardless_mandate_not_ready'));
            }
        } catch (\Exception $exception) {
            throw new \App\Exceptions\PaymentFailed($exception->getMessage());
        }
    }

    public function importCustomers()
    {
        ImportGoCardlessCustomers::dispatch($this->company_gateway->company->company_key, $this->company_gateway->id);
    }

    public function verificationView()
    {
        return render('gateways.gocardless.verification');
    }


    public function auth(): string
    {
        try {
            $customers = $this->init()->gateway->customers()->list();
            return 'ok';
        } catch (\Exception $e) {

        }

        return 'error';
    }
}
