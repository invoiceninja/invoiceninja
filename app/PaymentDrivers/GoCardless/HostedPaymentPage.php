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

namespace App\PaymentDrivers\GoCardless;

use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use GoCardlessPro\Resources\BillingRequest;
use GoCardlessPro\Resources\BillingRequestFlow;
use GoCardlessPro\Resources\CustomerBankAccount;
use GoCardlessPro\Resources\Mandate;
use GoCardlessPro\Resources\Payment as GoCardlessPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class HostedPaymentPage
{
    public const FLOW_EXPIRY_DAYS = 7;

    public const SCHEMES = [
        'AUD' => 'becs',
        'CAD' => 'pad',
        'DKK' => 'betalingsservice',
        'EUR' => 'sepa_core',
        'GBP' => 'bacs',
        'NZD' => 'becs_nz',
        'SEK' => 'autogiro',
        'USD' => 'ach',
    ];

    public const SEPA_COUNTRIES = [
        'AT', 'BE', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'IE',
        'IT', 'LT', 'LU', 'LV', 'MC', 'MT', 'NL', 'PT', 'SI', 'SK', 'SM',
    ];

    public function __construct(
        private GoCardlessPaymentDriver $driver,
        private ?string $context_id = null,
        private bool $payment_mode = true,
    ) {}

    public function start(int $gateway_type_id): string
    {
        $billing_request = $this->create($gateway_type_id);
        $billing_request_flow = $this->billingRequestFlowCall('create', [[
            'params' => [
                'redirect_uri' => $this->redirectUrl(),
                'exit_uri' => $this->exitUrl(),
                'prefilled_customer' => [
                    'country_code' => $this->driver->client->country->iso_3166_2,
                ],
                'links' => ['billing_request' => $billing_request->id],
                'show_redirect_buttons' => true,
                'show_success_redirect_button' => true,
            ],
            'headers' => ['Idempotency-Key' => $this->actionIdempotencyKey('flow')],
        ]]);

        $this->persistState([
            'billing_request' => $billing_request->id,
            'billing_request_flow' => $billing_request_flow->id,
            'gateway_type_id' => $gateway_type_id,
        ]);

        return (string) $billing_request_flow->authorisation_url;
    }

    public function create(int $gateway_type_id): BillingRequest
    {
        $amount = $this->payment_mode
            ? $this->driver->payment_hash->amount_with_fee()
            : -1;
        $this->driver->ensurePaymentMethodAvailable($gateway_type_id, $amount);

        if ($billing_request_id = $this->storedBillingRequestId()) {
            return $this->get($billing_request_id);
        }

        $currency = $this->driver->client->getCurrencyCode();
        $params = [];

        if ($this->payment_mode) {
            $params['metadata'] = ['payment_hash' => $this->driver->payment_hash->hash];
        }

        if ($gateway_type_id === GatewayType::INSTANT_BANK_PAY) {
            $params['payment_request'] = [
                'amount' => $this->driver->convertToGoCardlessAmount(
                    $this->driver->payment_hash->amount_with_fee(),
                    $this->driver->client->currency()->precision,
                ),
                'currency' => $currency,
                'scheme' => $this->instantPaymentScheme($currency),
                'description' => $this->description(),
            ];
        } else {
            $scheme = $this->scheme($currency);
            $params['mandate_request'] = [
                'currency' => $currency,
                'scheme' => $scheme,
                'verify' => $this->driver->company_gateway->getConfigField('verifyBankAccount') ? 'recommended' : 'minimum',
                'metadata' => ['client_hash' => $this->driver->client->client_hash],
            ];

            if (in_array($scheme, ['ach', 'pad'], true)) {
                $params['mandate_request']['consent_type'] = 'recurring';
                $params['mandate_request']['constraints'] = [
                    'payment_method' => 'Variable invoice amounts communicated to the payer before each debit.',
                ];
            }
        }

        $billing_request = $this->billingRequestCall('create', [[
            'params' => $params,
            'headers' => ['Idempotency-Key' => $this->actionIdempotencyKey('create')],
        ]]);

        $this->persistState([
            'billing_request' => $billing_request->id,
            'gateway_type_id' => $gateway_type_id,
        ]);

        return $billing_request;
    }

    public function get(string $billing_request_id): BillingRequest
    {
        return $this->billingRequestCall('get', [$billing_request_id]);
    }

    public function complete(BillingRequest $billing_request): ?Payment
    {
        return Cache::lock("gocardless-billing-request-{$billing_request->id}", 30)
            ->block(5, fn(): ?Payment => $this->completeUnlocked($billing_request));
    }

    public function completeSetup(BillingRequest $billing_request): ?ClientGatewayToken
    {
        return Cache::lock("gocardless-billing-request-{$billing_request->id}", 30)
            ->block(5, fn(): ?ClientGatewayToken => $this->completeSetupUnlocked($billing_request));
    }

    private function completeSetupUnlocked(BillingRequest $billing_request): ?ClientGatewayToken
    {
        if (! in_array($billing_request->status, ['fulfilling', 'fulfilled'], true)) {
            return null;
        }

        $mandate_id = $this->mandateId($billing_request);

        return $mandate_id ? $this->syncMandate($mandate_id) : null;
    }

    private function completeUnlocked(BillingRequest $billing_request): ?Payment
    {
        if (! in_array($billing_request->status, ['fulfilling', 'fulfilled'], true)) {
            return null;
        }

        $mandate_id = $this->mandateId($billing_request);

        if ($mandate_id) {
            $this->syncMandate($mandate_id);
        }

        $payment_id = data_get($billing_request, 'payment_request.links.payment')
            ?? data_get($billing_request, 'links.payment_request_payment')
            ?? data_get($billing_request, 'resources.payment.id')
            ?? data_get($billing_request, 'resources.payment');

        if ($payment_id) {
            $payment = $this->paymentCall('get', [$payment_id]);

            return $this->createLocalPayment($payment, GatewayType::INSTANT_BANK_PAY, PaymentType::INSTANT_BANK_PAY);
        }

        if (! $mandate_id) {
            return null;
        }

        $payment = $this->paymentCall('create', [[
            'params' => [
                'amount' => $this->driver->convertToGoCardlessAmount(
                    $this->driver->payment_hash->amount_with_fee(),
                    $this->driver->client->currency()->precision,
                ),
                'currency' => $this->driver->client->getCurrencyCode(),
                'description' => $this->description(),
                'metadata' => ['payment_hash' => $this->driver->payment_hash->hash],
                'links' => ['mandate' => $mandate_id],
            ],
            'headers' => ['Idempotency-Key' => $this->paymentIdempotencyKey()],
        ]]);

        $scheme = data_get($billing_request, 'mandate_request.scheme');

        return $this->createLocalPayment(
            $payment,
            self::gatewayTypeForScheme($scheme),
            self::paymentTypeForScheme($scheme),
        );
    }

    private function mandateId(BillingRequest $billing_request): ?string
    {
        return data_get($billing_request, 'mandate_request.links.mandate')
            ?? data_get($billing_request, 'links.mandate_request_mandate')
            ?? data_get($billing_request, 'resources.mandate.id')
            ?? data_get($billing_request, 'resources.mandate');
    }

    public function scheme(string $currency): string
    {
        return self::SCHEMES[$currency] ?? throw new \InvalidArgumentException("Unsupported GoCardless currency: {$currency}");
    }

    public function instantPaymentScheme(string $currency): string
    {
        return match ($currency) {
            'GBP' => 'faster_payments',
            'EUR' => 'sepa_credit_transfer',
            default => throw new \InvalidArgumentException("Unsupported GoCardless instant payment currency: {$currency}"),
        };
    }

    private function description(): string
    {
        $numbers = collect($this->driver->payment_hash->invoices())
            ->pluck('invoice_number')
            ->filter()
            ->implode(', ');

        return trim(ctrans('texts.invoices') . ': ' . $numbers);
    }

    public function syncMandate(string $mandate_id): ClientGatewayToken
    {
        return Cache::lock("gocardless-mandate-{$mandate_id}", 30)
            ->block(5, fn(): ClientGatewayToken => $this->syncMandateUnlocked($mandate_id));
    }

    public function replaceMandate(string $mandate_id, string $new_mandate_id): ClientGatewayToken
    {
        return Cache::lock("gocardless-mandate-{$new_mandate_id}", 30)
            ->block(5, function () use ($mandate_id, $new_mandate_id): ClientGatewayToken {
                $replacement = ClientGatewayToken::query()
                    ->withTrashed()
                    ->where('company_gateway_id', $this->driver->company_gateway->id)
                    ->where('token', $new_mandate_id)
                    ->first();

                if ($replacement) {
                    return $this->syncMandateUnlocked($new_mandate_id, $replacement);
                }

                $existing = ClientGatewayToken::query()
                    ->withTrashed()
                    ->where('company_gateway_id', $this->driver->company_gateway->id)
                    ->where('token', $mandate_id)
                    ->first();

                return $this->syncMandateUnlocked($new_mandate_id, $existing);
            });
    }

    private function syncMandateUnlocked(
        string $mandate_id,
        ?ClientGatewayToken $existing = null,
    ): ClientGatewayToken {
        $mandate = $this->mandateCall('get', [$mandate_id]);
        $bank_account_id = data_get($mandate, 'links.customer_bank_account');
        $bank_account = $bank_account_id
            ? $this->customerBankAccountCall('get', [$bank_account_id])
            : null;
        $gateway_type_id = self::gatewayTypeForScheme($mandate->scheme);
        $existing ??= ClientGatewayToken::query()
            ->withTrashed()
            ->where('company_gateway_id', $this->driver->company_gateway->id)
            ->where('token', $mandate_id)
            ->first();

        if ($existing) {
            if ($existing->client_id !== $this->driver->client->id) {
                throw new \UnexpectedValueException('GoCardless mandate is already linked to another client.');
            }

            if ($existing->trashed()) {
                $existing->restore();
            }

            $meta = $existing->meta ?? new \stdClass();
            $meta->brand = $bank_account?->bank_name ?: ($meta->brand ?? strtoupper(str_replace('_', ' ', $mandate->scheme)));
            $meta->scheme = $mandate->scheme;
            $meta->type = $gateway_type_id;
            $meta->state = $this->mandateState($mandate->status, $meta->state ?? 'pending');
            $meta->last4 = $bank_account
                ? $bank_account->account_number_ending
                : ($meta->last4 ?? null);
            $existing->token = $mandate_id;
            $existing->gateway_customer_reference = data_get($mandate, 'links.customer');
            $existing->gateway_type_id = $gateway_type_id;
            $existing->meta = $meta;
            $existing->save();

            return $existing;
        }

        $meta = new \stdClass();
        $meta->brand = $bank_account?->bank_name ?: strtoupper(str_replace('_', ' ', $mandate->scheme));
        $meta->scheme = $mandate->scheme;
        $meta->type = $gateway_type_id;
        $meta->state = $this->mandateState($mandate->status);
        $meta->last4 = $bank_account?->account_number_ending;

        $token = $this->driver->storeGatewayToken([
            'payment_meta' => $meta,
            'token' => $mandate_id,
            'payment_method_id' => $gateway_type_id,
        ], [
            'gateway_customer_reference' => data_get($mandate, 'links.customer'),
        ]);

        return $token ?? throw new \UnexpectedValueException('GoCardless mandate could not be stored.');
    }

    private function mandateState(string $status, string $current_state = 'pending'): string
    {
        return match ($status) {
            'active' => 'authorized',
            'pending_customer_approval', 'pending_submission', 'submitted' => 'pending',
            'blocked', 'cancelled', 'consumed', 'expired', 'failed', 'suspended_by_payer' => $status,
            default => $current_state,
        };
    }

    private function createLocalPayment(object $payment, int $gateway_type_id, int $payment_type_id): Payment
    {
        if (in_array(data_get($payment, 'status'), ['cancelled', 'charged_back', 'failed'], true)) {
            throw new PaymentFailed('GoCardless could not process the payment.');
        }

        $status = in_array(data_get($payment, 'status'), ['confirmed', 'paid_out'], true)
            ? Payment::STATUS_COMPLETED
            : Payment::STATUS_PENDING;

        return $this->driver->createPayment([
            'payment_method' => data_get($payment, 'links.mandate'),
            'payment_type' => $payment_type_id,
            'amount' => $this->driver->payment_hash->amount_with_fee(),
            'transaction_reference' => $payment->id,
            'gateway_type_id' => $gateway_type_id,
        ], $status);
    }

    public static function gatewayTypeForScheme(?string $scheme): int
    {
        return match ($scheme) {
            'ach' => GatewayType::BANK_TRANSFER,
            'sepa_core' => GatewayType::SEPA,
            default => GatewayType::DIRECT_DEBIT,
        };
    }

    public static function paymentTypeForScheme(?string $scheme): int
    {
        return match ($scheme) {
            'ach' => PaymentType::ACH,
            'bacs' => PaymentType::BACS,
            'becs' => PaymentType::BECS,
            'pad' => PaymentType::ACSS,
            'sepa_core' => PaymentType::SEPA,
            'faster_payments', 'sepa_credit_transfer', 'sepa_instant_credit_transfer' => PaymentType::INSTANT_BANK_PAY,
            default => PaymentType::DIRECT_DEBIT,
        };
    }

    public static function paymentTypeForGatewayType(int $gateway_type_id): int
    {
        return match ($gateway_type_id) {
            GatewayType::BANK_TRANSFER => PaymentType::ACH,
            GatewayType::SEPA => PaymentType::SEPA,
            GatewayType::ACSS => PaymentType::ACSS,
            GatewayType::BECS => PaymentType::BECS,
            GatewayType::BACS => PaymentType::BACS,
            GatewayType::INSTANT_BANK_PAY => PaymentType::INSTANT_BANK_PAY,
            default => PaymentType::DIRECT_DEBIT,
        };
    }

    public static function paymentTypeForStoredMandate(int $gateway_type_id, string $currency, ?string $scheme): int
    {
        if ($gateway_type_id !== GatewayType::DIRECT_DEBIT) {
            return self::paymentTypeForGatewayType($gateway_type_id);
        }

        $scheme ??= self::SCHEMES[$currency]
            ?? throw new \InvalidArgumentException("Unsupported GoCardless currency: {$currency}");

        return self::paymentTypeForScheme($scheme);
    }

    private function redirectUrl(): string
    {
        $company_gateway = $this->driver->company_gateway;

        if ($this->payment_mode) {
            return URL::temporarySignedRoute('gocardless.hosted_payment_page.return', now()->addDays(self::FLOW_EXPIRY_DAYS), [
                'company_key' => $company_gateway->company->company_key,
                'company_gateway_id' => $company_gateway->hashed_id,
                'hash' => $this->driver->payment_hash->hash,
            ]);
        }

        return URL::temporarySignedRoute('gocardless.hosted_payment_page.setup_return', now()->addDays(self::FLOW_EXPIRY_DAYS), [
            'company_key' => $company_gateway->company->company_key,
            'company_gateway_id' => $company_gateway->hashed_id,
            'context' => $this->context_id,
        ]);
    }

    private function exitUrl(): string
    {
        if (! $this->payment_mode) {
            return route('client.payment_methods.index');
        }

        $invoice_id = data_get($this->driver->payment_hash->data, 'invoices.0.invoice_id');

        return $invoice_id
            ? route('client.invoice.show', ['invoice' => $invoice_id])
            : route('client.payment_methods.index');
    }

    private function storedBillingRequestId(): ?string
    {
        if ($this->payment_mode) {
            return data_get($this->driver->payment_hash->data, 'gocardless.billing_request');
        }

        return data_get(Cache::get($this->context_id, []), 'gocardless.billing_request');
    }

    private function persistState(array $state): void
    {
        if ($this->payment_mode) {
            $this->driver->payment_hash->withData('client_id', $this->driver->client->id);
            $this->driver->payment_hash->withData('gocardless', array_merge(
                (array) data_get($this->driver->payment_hash->data, 'gocardless', []),
                $state,
            ));

            return;
        }

        $context = Cache::get($this->context_id, []);
        data_set($context, 'gocardless', array_merge((array) data_get($context, 'gocardless', []), $state));
        Cache::put($this->context_id, $context, now()->addDays(self::FLOW_EXPIRY_DAYS));
    }

    private function paymentIdempotencyKey(): string
    {
        return substr(hash('sha256', 'gocardless-payment-' . $this->idempotencyContext()), 0, 32);
    }

    private function actionIdempotencyKey(string $action): string
    {
        return substr(hash('sha256', "gocardless-{$action}-{$this->idempotencyContext()}"), 0, 32);
    }

    private function idempotencyContext(): string
    {
        return $this->context_id ?? $this->driver->payment_hash->hash;
    }

    private function billingRequestCall(string $method, array $arguments): BillingRequest
    {
        $result = $this->driver->gateway->billingRequests()->{$method}(...$arguments);

        if (! $result instanceof BillingRequest) {
            throw new \UnexpectedValueException('GoCardless returned an invalid billing request.');
        }

        return $result;
    }

    private function billingRequestFlowCall(string $method, array $arguments): BillingRequestFlow
    {
        $result = $this->driver->gateway->billingRequestFlows()->{$method}(...$arguments);

        if (! $result instanceof BillingRequestFlow) {
            throw new \UnexpectedValueException('GoCardless returned an invalid billing request flow.');
        }

        return $result;
    }

    private function mandateCall(string $method, array $arguments): Mandate
    {
        $result = $this->driver->gateway->mandates()->{$method}(...$arguments);

        if (! $result instanceof Mandate) {
            throw new \UnexpectedValueException('GoCardless returned an invalid mandate.');
        }

        return $result;
    }

    private function customerBankAccountCall(string $method, array $arguments): CustomerBankAccount
    {
        $result = $this->driver->gateway->customerBankAccounts()->{$method}(...$arguments);

        if (! $result instanceof CustomerBankAccount) {
            throw new \UnexpectedValueException('GoCardless returned an invalid customer bank account.');
        }

        return $result;
    }

    private function paymentCall(string $method, array $arguments): GoCardlessPayment
    {
        $result = $this->driver->gateway->payments()->{$method}(...$arguments);

        if (! $result instanceof GoCardlessPayment) {
            throw new \UnexpectedValueException('GoCardless returned an invalid payment.');
        }

        return $result;
    }
}
