<?php

namespace Tests\Feature\PaymentDrivers\GoCardless;

use App\DataMapper\FeesAndLimits;
use App\Exceptions\PaymentFailed;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\GoCardless\HostedPaymentPage;
use App\PaymentDrivers\GoCardless\DirectDebit;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use App\Services\Client\ClientService;
use App\Jobs\Util\SystemLogger;
use Composer\InstalledVersions;
use GoCardlessPro\Client as GoCardlessClient;
use GoCardlessPro\Resources\BillingRequest;
use GoCardlessPro\Resources\BillingRequestFlow;
use GoCardlessPro\Resources\CustomerBankAccount;
use GoCardlessPro\Resources\Mandate;
use GoCardlessPro\Resources\Payment as GoCardlessPayment;
use GoCardlessPro\Services\BillingRequestsService;
use GoCardlessPro\Services\BillingRequestFlowsService;
use GoCardlessPro\Services\CustomerBankAccountsService;
use GoCardlessPro\Services\MandatesService;
use GoCardlessPro\Services\PaymentsService;
use GoCardlessPro\Webhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class HostedPaymentPageTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private HostedPaymentPage $service;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([SystemLogger::class]);
        $this->service = new HostedPaymentPage(Mockery::mock(GoCardlessPaymentDriver::class));
    }

    public function test_it_maps_every_supported_direct_debit_currency_to_a_scheme(): void
    {
        $schemes = [
            'AUD' => 'becs',
            'CAD' => 'pad',
            'DKK' => 'betalingsservice',
            'EUR' => 'sepa_core',
            'GBP' => 'bacs',
            'NZD' => 'becs_nz',
            'SEK' => 'autogiro',
            'USD' => 'ach',
        ];

        foreach ($schemes as $currency => $scheme) {
            $this->assertSame($scheme, $this->service->scheme($currency));
        }
    }

    public function test_it_uses_the_gocardless_v8_billing_request_api(): void
    {
        $version = InstalledVersions::getPrettyVersion('gocardless/gocardless-pro');

        $this->assertNotNull($version);
        $this->assertStringStartsWith('8.1.', $version);

        foreach ([
            'collectCustomerDetails',
            'collectBankAccount',
            'confirmPayerDetails',
            'selectInstitution',
            'fulfil',
        ] as $method) {
            $this->assertTrue(method_exists(BillingRequestsService::class, $method));
        }

        $this->assertTrue(method_exists(Webhook::class, 'parseWithMeta'));

        $payload = json_encode([
            'events' => [[
                'id' => 'EV123',
                'action' => 'confirmed',
                'resource_type' => 'payments',
                'links' => ['payment' => 'PM123'],
            ]],
            'meta' => ['webhook_id' => 'WB123'],
        ], JSON_THROW_ON_ERROR);
        $webhook = Webhook::parseWithMeta($payload, hash_hmac('sha256', $payload, 'secret'), 'secret');

        $this->assertSame('WB123', $webhook->getWebhookId());
        $this->assertSame('EV123', $webhook->getEvents()[0]->id);
    }

    public function test_it_rejects_an_unsupported_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->scheme('JPY');
    }

    public function test_it_maps_each_instant_payment_currency_to_the_current_scheme(): void
    {
        $this->assertSame('faster_payments', $this->service->instantPaymentScheme('GBP'));
        $this->assertSame('sepa_credit_transfer', $this->service->instantPaymentScheme('EUR'));
    }

    #[DataProvider('gatewayAvailabilityProvider')]
    public function test_gateway_types_are_limited_to_the_client_country_and_currency(
        string $country_code,
        string $currency_code,
        array $expected,
    ): void {
        $currency = new \App\Models\Currency();
        $currency->code = $currency_code;
        $country = new Country();
        $country->iso_3166_2 = $country_code;
        $client = Mockery::mock(Client::class)->makePartial();
        $client->country = $country;
        $client->shouldReceive('currency')->andReturn($currency);
        $driver = new GoCardlessPaymentDriver(Mockery::mock(CompanyGateway::class), $client);

        $this->assertSame($expected, $driver->gatewayTypes());
    }

    public static function gatewayAvailabilityProvider(): array
    {
        return [
            'US ACH' => ['US', 'USD', [GatewayType::BANK_TRANSFER]],
            'US client with GBP' => ['US', 'GBP', []],
            'Australian BECS' => ['AU', 'AUD', [GatewayType::DIRECT_DEBIT]],
            'Australian client with CAD' => ['AU', 'CAD', []],
            'Canadian PAD' => ['CA', 'CAD', [GatewayType::DIRECT_DEBIT]],
            'Danish Betalingsservice' => ['DK', 'DKK', [GatewayType::DIRECT_DEBIT]],
            'British Bacs and Faster Payments' => ['GB', 'GBP', [GatewayType::DIRECT_DEBIT, GatewayType::INSTANT_BANK_PAY]],
            'New Zealand BECS' => ['NZ', 'NZD', [GatewayType::DIRECT_DEBIT]],
            'Swedish Autogiro' => ['SE', 'SEK', [GatewayType::DIRECT_DEBIT]],
            'German SEPA methods' => ['DE', 'EUR', [GatewayType::SEPA, GatewayType::INSTANT_BANK_PAY]],
            'French SEPA methods' => ['FR', 'EUR', [GatewayType::SEPA, GatewayType::INSTANT_BANK_PAY]],
            'Irish SEPA methods' => ['IE', 'EUR', [GatewayType::SEPA, GatewayType::INSTANT_BANK_PAY]],
            'Austrian SEPA without Instant Bank Pay' => ['AT', 'EUR', [GatewayType::SEPA]],
            'non-SEPA client with EUR' => ['US', 'EUR', []],
        ];
    }

    public function test_every_supported_sepa_country_uses_only_the_sepa_gateway_type(): void
    {
        foreach (HostedPaymentPage::SEPA_COUNTRIES as $country_code) {
            $currency = new \App\Models\Currency();
            $currency->code = 'EUR';
            $country = new Country();
            $country->iso_3166_2 = $country_code;
            $client = Mockery::mock(Client::class)->makePartial();
            $client->country = $country;
            $client->shouldReceive('currency')->andReturn($currency);
            $driver = new GoCardlessPaymentDriver(Mockery::mock(CompanyGateway::class), $client);
            $gateway_types = $driver->gatewayTypes();

            $this->assertContains(GatewayType::SEPA, $gateway_types, $country_code);
            $this->assertNotContains(GatewayType::DIRECT_DEBIT, $gateway_types, $country_code);

            if (in_array($country_code, ['IE', 'FR', 'DE'], true)) {
                $this->assertContains(GatewayType::INSTANT_BANK_PAY, $gateway_types, $country_code);
            } else {
                $this->assertNotContains(GatewayType::INSTANT_BANK_PAY, $gateway_types, $country_code);
            }
        }
    }

    public function test_eur_client_cannot_bypass_sepa_availability_with_direct_debit(): void
    {
        $payment_methods = Mockery::mock(ClientService::class);
        $payment_methods->shouldReceive('getPaymentMethods')
            ->once()
            ->with(42.0)
            ->andReturn([[
                'company_gateway_id' => 100,
                'gateway_type_id' => GatewayType::SEPA,
            ]]);
        $client = Mockery::mock(Client::class)->makePartial();
        $client->shouldReceive('service')->once()->andReturn($payment_methods);
        $company_gateway = Mockery::mock(CompanyGateway::class)->makePartial();
        $company_gateway->id = 100;
        $driver = new GoCardlessPaymentDriver($company_gateway, $client);

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionCode(403);

        $driver->ensurePaymentMethodAvailable(GatewayType::DIRECT_DEBIT, 42.0);
    }

    public function test_driver_exposes_payment_schemes_through_existing_gateway_types(): void
    {
        $this->assertSame([
            GatewayType::BANK_TRANSFER,
            GatewayType::DIRECT_DEBIT,
            GatewayType::SEPA,
            GatewayType::INSTANT_BANK_PAY,
        ], array_keys(GoCardlessPaymentDriver::$methods));
    }

    public function test_billing_request_creation_cannot_bypass_payment_method_availability(): void
    {
        $driver = Mockery::mock(GoCardlessPaymentDriver::class);
        $driver->shouldReceive('ensurePaymentMethodAvailable')
            ->once()
            ->with(GatewayType::DIRECT_DEBIT, 10.0)
            ->andThrow(new PaymentFailed('Unavailable', 403));
        $payment_hash = Mockery::mock(PaymentHash::class);
        $payment_hash->shouldReceive('amount_with_fee')->once()->andReturn(10.0);
        $driver->payment_hash = $payment_hash;

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionCode(403);

        (new HostedPaymentPage($driver, 'test-context'))->create(GatewayType::DIRECT_DEBIT);
    }

    public function test_driver_requires_the_exact_advertised_company_gateway_and_type(): void
    {
        $payment_methods = Mockery::mock(ClientService::class);
        $payment_methods->shouldReceive('getPaymentMethods')
            ->twice()
            ->with(42.0)
            ->andReturn([[
                'company_gateway_id' => 100,
                'gateway_type_id' => GatewayType::BANK_TRANSFER,
            ]]);
        $client = Mockery::mock(Client::class)->makePartial();
        $client->shouldReceive('service')->twice()->andReturn($payment_methods);
        $company_gateway = Mockery::mock(CompanyGateway::class)->makePartial();
        $company_gateway->id = 100;
        $driver = new GoCardlessPaymentDriver($company_gateway, $client);

        $this->assertSame(
            $driver,
            $driver->ensurePaymentMethodAvailable(GatewayType::BANK_TRANSFER, 42.0),
        );

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionCode(403);

        $driver->ensurePaymentMethodAvailable(GatewayType::DIRECT_DEBIT, 42.0);
    }

    public function test_stored_mandate_is_resolved_only_for_the_current_client_gateway_and_type(): void
    {
        $this->makeTestData();

        $company_gateway = $this->makeCompanyGateway();
        $token = $this->createGatewayToken($company_gateway, $this->client, GatewayType::DIRECT_DEBIT, 'MD_OWNED');
        $driver = Mockery::mock(GoCardlessPaymentDriver::class, [$company_gateway, $this->client])->makePartial();
        $driver->shouldReceive('ensurePaymentMethodAvailable')->never();

        $resolved_token = $driver->resolveClientGatewayToken('MD_OWNED', GatewayType::DIRECT_DEBIT);

        $this->assertTrue($resolved_token->is($token));
    }

    public function test_autobilling_resolves_an_owned_mandate_without_portal_availability_filters(): void
    {
        $this->makeTestData();

        $company_gateway = $this->makeCompanyGateway();
        $token = $this->createGatewayToken($company_gateway, $this->client, GatewayType::DIRECT_DEBIT, 'MD_AUTOBILL');
        $driver = Mockery::mock(GoCardlessPaymentDriver::class, [$company_gateway, $this->client])->makePartial();
        $driver->shouldReceive('ensurePaymentMethodAvailable')->never();

        $resolved_token = $driver->resolveOwnedClientGatewayToken('MD_AUTOBILL');

        $this->assertTrue($resolved_token->is($token));
    }

    public function test_legacy_imported_sepa_tokens_are_backfilled_to_the_sepa_gateway_type(): void
    {
        $this->makeTestData();

        $company_gateway = $this->makeCompanyGateway();
        $token = $this->createGatewayToken($company_gateway, $this->client, GatewayType::DIRECT_DEBIT, 'MD_IMPORTED_SEPA');
        $token->meta = (object) [
            'state' => 'authorized',
            'type' => GatewayType::SEPA,
        ];
        $token->saveQuietly();
        $migration = require database_path('migrations/2026_09_08_062029_correct_imported_gocardless_sepa_gateway_type.php');

        $migration->up();

        $token->refresh();
        $this->assertSame(GatewayType::SEPA, $token->gateway_type_id);
        $this->assertSame('sepa_core', data_get($token->meta, 'scheme'));
    }

    #[DataProvider('invalidStoredMandateProvider')]
    public function test_stored_mandate_cannot_be_used_outside_its_owner_scope(string $mismatch): void
    {
        $this->makeTestData();

        $company_gateway = $this->makeCompanyGateway();
        $token_company_gateway = $company_gateway;
        $token_client = $this->client;
        $token_gateway_type_id = GatewayType::DIRECT_DEBIT;

        if ($mismatch === 'client') {
            $token_client = Client::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
            ]);
        } elseif ($mismatch === 'gateway') {
            $token_company_gateway = $this->makeCompanyGateway();
        } else {
            $token_gateway_type_id = GatewayType::SEPA;
        }

        $this->createGatewayToken($token_company_gateway, $token_client, $token_gateway_type_id, 'MD_FOREIGN');
        $driver = Mockery::mock(GoCardlessPaymentDriver::class, [$company_gateway, $this->client])->makePartial();
        $driver->shouldReceive('ensurePaymentMethodAvailable')->never();

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionCode(403);

        $driver->resolveClientGatewayToken('MD_FOREIGN', GatewayType::DIRECT_DEBIT);
    }

    public static function invalidStoredMandateProvider(): array
    {
        return [
            'another client' => ['client'],
            'another company gateway' => ['gateway'],
            'another gateway type' => ['gateway_type'],
        ];
    }

    public function test_stored_payment_rejects_an_unowned_mandate_before_calling_gocardless(): void
    {
        $payment_hash = Mockery::mock(PaymentHash::class);
        $driver = Mockery::mock(GoCardlessPaymentDriver::class);
        $driver->payment_hash = $payment_hash;
        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldReceive('resolveClientGatewayToken')
            ->once()
            ->with('MD_FOREIGN', GatewayType::DIRECT_DEBIT)
            ->andThrow(new PaymentFailed('Unavailable', 403));
        $driver->shouldReceive('ensureMandateIsReady')->never();
        $request = new \App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest([
            'source' => 'MD_FOREIGN',
            'payment_method_id' => GatewayType::DIRECT_DEBIT,
        ]);

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionCode(403);

        (new DirectDebit($driver))->paymentResponse($request);
    }

    #[DataProvider('paymentTypeProvider')]
    public function test_every_gocardless_payment_scheme_maps_to_the_correct_invoice_ninja_type(
        string $scheme,
        int $payment_type_id,
    ): void {
        $this->assertSame($payment_type_id, HostedPaymentPage::paymentTypeForScheme($scheme));
    }

    public static function paymentTypeProvider(): array
    {
        return [
            'ACH' => ['ach', PaymentType::ACH],
            'Autogiro' => ['autogiro', PaymentType::DIRECT_DEBIT],
            'Bacs' => ['bacs', PaymentType::BACS],
            'BECS' => ['becs', PaymentType::BECS],
            'BECS NZ' => ['becs_nz', PaymentType::DIRECT_DEBIT],
            'Betalingsservice' => ['betalingsservice', PaymentType::DIRECT_DEBIT],
            'Faster Payments' => ['faster_payments', PaymentType::INSTANT_BANK_PAY],
            'PAD' => ['pad', PaymentType::ACSS],
            'PayTo' => ['pay_to', PaymentType::DIRECT_DEBIT],
            'SEPA Core' => ['sepa_core', PaymentType::SEPA],
            'SEPA Credit Transfer' => ['sepa_credit_transfer', PaymentType::INSTANT_BANK_PAY],
            'SEPA Instant Credit Transfer' => ['sepa_instant_credit_transfer', PaymentType::INSTANT_BANK_PAY],
        ];
    }

    #[DataProvider('storedMandatePaymentTypeProvider')]
    public function test_stored_mandates_map_to_the_correct_payment_type(
        int $gateway_type_id,
        string $currency,
        ?string $scheme,
        int $payment_type_id,
    ): void {
        $this->assertSame(
            $payment_type_id,
            HostedPaymentPage::paymentTypeForStoredMandate($gateway_type_id, $currency, $scheme),
        );
    }

    public static function storedMandatePaymentTypeProvider(): array
    {
        return [
            'stored scheme takes precedence' => [GatewayType::DIRECT_DEBIT, 'AUD', 'bacs', PaymentType::BACS],
            'legacy Bacs token' => [GatewayType::DIRECT_DEBIT, 'GBP', null, PaymentType::BACS],
            'legacy BECS token' => [GatewayType::DIRECT_DEBIT, 'AUD', null, PaymentType::BECS],
            'legacy PAD token' => [GatewayType::DIRECT_DEBIT, 'CAD', null, PaymentType::ACSS],
            'legacy BECS NZ token' => [GatewayType::DIRECT_DEBIT, 'NZD', null, PaymentType::DIRECT_DEBIT],
            'legacy Betalingsservice token' => [GatewayType::DIRECT_DEBIT, 'DKK', null, PaymentType::DIRECT_DEBIT],
            'legacy Autogiro token' => [GatewayType::DIRECT_DEBIT, 'SEK', null, PaymentType::DIRECT_DEBIT],
            'ACH token' => [GatewayType::BANK_TRANSFER, 'USD', null, PaymentType::ACH],
            'SEPA token' => [GatewayType::SEPA, 'EUR', null, PaymentType::SEPA],
        ];
    }

    #[DataProvider('tokenBillingProvider')]
    public function test_token_billing_supports_every_mandate_scheme_and_payment_state(
        string $currency_code,
        string $scheme,
        int $gateway_type_id,
        int $payment_type_id,
        string $remote_status,
        int $local_status,
    ): void {
        $currency = new \App\Models\Currency();
        $currency->code = $currency_code;
        $currency->precision = 2;
        $client = Mockery::mock(Client::class)->makePartial();
        $client->company = new \App\Models\Company();
        $client->shouldReceive('currency')->andReturn($currency);
        $client->shouldReceive('getCurrencyCode')->andReturn($currency_code);
        $client->shouldReceive('present->name')->andReturn('Test Client');
        $company_gateway = Mockery::mock(CompanyGateway::class);
        $token = Mockery::mock(ClientGatewayToken::class)->makePartial();
        $token->id = 1;
        $token->token = 'MD_TOKEN_BILLING';
        $token->gateway_type_id = $gateway_type_id;
        $token->meta = (object) ['scheme' => $scheme];
        $payment_hash = Mockery::mock(PaymentHash::class)->makePartial();
        $payment_hash->hash = 'token-billing-hash';
        $payment_hash->fee_total = 0;
        $payment_hash->fee_invoice = null;
        $payment_hash->shouldReceive('invoices')->andReturn([['amount' => 42.0]]);
        $remote_payment = new GoCardlessPayment((object) [
            'id' => 'PM_TOKEN_BILLING',
            'status' => $remote_status,
            'links' => (object) ['mandate' => 'MD_TOKEN_BILLING'],
        ]);
        $payments = Mockery::mock(PaymentsService::class);
        $payments->shouldReceive('create')->once()->andReturn($remote_payment);
        $gateway = Mockery::mock(GoCardlessClient::class);
        $gateway->shouldReceive('payments')->once()->andReturn($payments);
        $local_payment = new \App\Models\Payment();
        $driver = Mockery::mock(GoCardlessPaymentDriver::class, [$company_gateway, $client])->makePartial();
        $driver->gateway = $gateway;
        $driver->shouldReceive('ensurePaymentMethodAvailable')->never();
        $driver->shouldReceive('resolveOwnedClientGatewayToken')
            ->once()
            ->with('MD_TOKEN_BILLING')
            ->andReturn($token);
        $driver->shouldReceive('convertToGoCardlessAmount')->once()->with(42.0, 2)->andReturn(4200);
        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldReceive('confirmGatewayFee')->once();
        $driver->shouldReceive('createPayment')
            ->once()
            ->withArgs(function (array $data, int $status) use ($payment_type_id, $gateway_type_id, $local_status): bool {
                return $data['payment_type'] === $payment_type_id
                    && $data['gateway_type_id'] === $gateway_type_id
                    && $data['transaction_reference'] === 'PM_TOKEN_BILLING'
                    && $status === $local_status;
            })
            ->andReturn($local_payment);

        $this->assertSame($local_payment, $driver->tokenBilling($token, $payment_hash));
    }

    public static function tokenBillingProvider(): array
    {
        $schemes = [
            ['USD', 'ach', GatewayType::BANK_TRANSFER, PaymentType::ACH],
            ['GBP', 'bacs', GatewayType::DIRECT_DEBIT, PaymentType::BACS],
            ['AUD', 'becs', GatewayType::DIRECT_DEBIT, PaymentType::BECS],
            ['NZD', 'becs_nz', GatewayType::DIRECT_DEBIT, PaymentType::DIRECT_DEBIT],
            ['CAD', 'pad', GatewayType::DIRECT_DEBIT, PaymentType::ACSS],
            ['DKK', 'betalingsservice', GatewayType::DIRECT_DEBIT, PaymentType::DIRECT_DEBIT],
            ['SEK', 'autogiro', GatewayType::DIRECT_DEBIT, PaymentType::DIRECT_DEBIT],
            ['EUR', 'sepa_core', GatewayType::SEPA, PaymentType::SEPA],
        ];
        $cases = [];

        foreach ($schemes as [$currency_code, $scheme, $gateway_type_id, $payment_type_id]) {
            $cases["{$scheme} pending"] = [$currency_code, $scheme, $gateway_type_id, $payment_type_id, 'pending_submission', \App\Models\Payment::STATUS_PENDING];
            $cases["{$scheme} confirmed"] = [$currency_code, $scheme, $gateway_type_id, $payment_type_id, 'confirmed', \App\Models\Payment::STATUS_COMPLETED];
        }

        return $cases;
    }

    public function test_it_rejects_an_unsupported_instant_payment_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->instantPaymentScheme('AUD');
    }

    public function test_direct_debit_payment_creates_a_mandate_then_payment_even_when_instant_bank_pay_is_available(): void
    {
        $payload = $this->captureCreatePayload('GBP', 'GB', GatewayType::DIRECT_DEBIT, true);

        $this->assertSame('bacs', data_get($payload, 'mandate_request.scheme'));
        $this->assertSame('minimum', data_get($payload, 'mandate_request.verify'));
        $this->assertSame('client-hash', data_get($payload, 'mandate_request.metadata.client_hash'));
        $this->assertArrayNotHasKey('payment_request', $payload);
        $this->assertSame('payment-hash', data_get($payload, 'metadata.payment_hash'));
    }

    public function test_bank_account_verification_uses_the_recommended_preference_when_enabled(): void
    {
        $payload = $this->captureCreatePayload('USD', 'US', GatewayType::DIRECT_DEBIT, true, true);

        $this->assertSame('recommended', data_get($payload, 'mandate_request.verify'));
    }

    public function test_direct_debit_payment_falls_back_to_mandate_then_payment_for_non_instant_schemes(): void
    {
        $payload = $this->captureCreatePayload('USD', 'US', GatewayType::BANK_TRANSFER, true);

        $this->assertSame('ach', data_get($payload, 'mandate_request.scheme'));
        $this->assertSame('recurring', data_get($payload, 'mandate_request.consent_type'));
        $this->assertSame(
            'Variable invoice amounts communicated to the payer before each debit.',
            data_get($payload, 'mandate_request.constraints.payment_method'),
        );
        $this->assertSame('client-hash', data_get($payload, 'mandate_request.metadata.client_hash'));
        $this->assertArrayNotHasKey('payment_request', $payload);
    }

    public function test_pad_uses_recurring_consent_for_reusable_invoice_payment_methods(): void
    {
        $payload = $this->captureCreatePayload('CAD', 'CA', GatewayType::DIRECT_DEBIT, false);

        $this->assertSame('pad', data_get($payload, 'mandate_request.scheme'));
        $this->assertSame('recurring', data_get($payload, 'mandate_request.consent_type'));
        $this->assertSame(
            'Variable invoice amounts communicated to the payer before each debit.',
            data_get($payload, 'mandate_request.constraints.payment_method'),
        );
    }

    public function test_instant_bank_pay_creates_a_payment_only_billing_request(): void
    {
        $payload = $this->captureCreatePayload('EUR', 'DE', GatewayType::INSTANT_BANK_PAY, true);

        $this->assertArrayNotHasKey('mandate_request', $payload);
        $this->assertSame('sepa_credit_transfer', data_get($payload, 'payment_request.scheme'));
    }

    public function test_payment_method_setup_creates_a_mandate_only_billing_request(): void
    {
        $payload = $this->captureCreatePayload('GBP', 'GB', GatewayType::DIRECT_DEBIT, false);

        $this->assertSame('bacs', data_get($payload, 'mandate_request.scheme'));
        $this->assertSame('client-hash', data_get($payload, 'mandate_request.metadata.client_hash'));
        $this->assertArrayNotHasKey('payment_request', $payload);
        $this->assertArrayNotHasKey('metadata', $payload);
    }

    public function test_sync_mandate_links_the_payment_method_to_the_driver_client(): void
    {
        $this->makeTestData();

        $service = $this->makeMandateSyncService(
            $this->makeCompanyGateway(),
            $this->client,
            'MD_SYNC_NEW',
            'active',
        );

        $token = $service->syncMandate('MD_SYNC_NEW');

        $this->assertSame($this->client->id, $token->client_id);
        $this->assertSame('MD_SYNC_NEW', $token->token);
        $this->assertSame('CU_SYNC', $token->gateway_customer_reference);
        $this->assertSame(GatewayType::DIRECT_DEBIT, $token->gateway_type_id);
        $this->assertSame('authorized', $token->meta->state);
        $this->assertSame('bacs', $token->meta->scheme);
        $this->assertSame('Test Bank', $token->meta->brand);
        $this->assertSame('1234', $token->meta->last4);
    }

    public function test_complete_setup_stores_the_fulfilled_mandate(): void
    {
        $this->makeTestData();

        $service = $this->makeMandateSyncService(
            $this->makeCompanyGateway(),
            $this->client,
            'MD_SETUP_COMPLETE',
            'submitted',
        );
        $billing_request = new BillingRequest((object) [
            'id' => 'BRQ_SETUP_COMPLETE',
            'status' => 'fulfilled',
            'mandate_request' => (object) [
                'links' => (object) ['mandate' => 'MD_SETUP_COMPLETE'],
            ],
        ]);

        $token = $service->completeSetup($billing_request);

        $this->assertNotNull($token);
        $this->assertSame($this->client->id, $token->client_id);
        $this->assertSame('MD_SETUP_COMPLETE', $token->token);
        $this->assertSame('pending', $token->meta->state);
    }

    #[DataProvider('mandate_status_provider')]
    public function test_sync_mandate_maps_the_current_gocardless_status(string $status, string $expected_state): void
    {
        $this->makeTestData();

        $mandate_id = 'MD_STATUS_' . strtoupper($status);
        $service = $this->makeMandateSyncService(
            $this->makeCompanyGateway(),
            $this->client,
            $mandate_id,
            $status,
        );

        $this->assertSame($expected_state, $service->syncMandate($mandate_id)->meta->state);
    }

    public static function mandate_status_provider(): array
    {
        return [
            'active' => ['active', 'authorized'],
            'pending customer approval' => ['pending_customer_approval', 'pending'],
            'pending submission' => ['pending_submission', 'pending'],
            'submitted' => ['submitted', 'pending'],
            'blocked' => ['blocked', 'blocked'],
            'cancelled' => ['cancelled', 'cancelled'],
            'consumed' => ['consumed', 'consumed'],
            'expired' => ['expired', 'expired'],
            'failed' => ['failed', 'failed'],
            'suspended by payer' => ['suspended_by_payer', 'suspended_by_payer'],
        ];
    }

    public function test_sync_mandate_is_idempotent_and_refreshes_the_existing_token(): void
    {
        $this->makeTestData();

        $company_gateway = $this->makeCompanyGateway();
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $company_gateway->id;
        $token->gateway_type_id = GatewayType::DIRECT_DEBIT;
        $token->token = 'MD_SYNC_EXISTING';
        $token->gateway_customer_reference = 'CU_OLD';
        $token->meta = (object) [
            'brand' => 'Old Bank',
            'last4' => '00',
            'state' => 'pending',
        ];
        $token->save();
        $service = $this->makeMandateSyncService(
            $company_gateway,
            $this->client,
            'MD_SYNC_EXISTING',
            'active',
            2,
        );

        $first = $service->syncMandate('MD_SYNC_EXISTING');
        $second = $service->syncMandate('MD_SYNC_EXISTING');

        $this->assertSame($token->id, $first->id);
        $this->assertSame($token->id, $second->id);
        $this->assertSame(1, ClientGatewayToken::query()
            ->where('company_gateway_id', $company_gateway->id)
            ->where('token', 'MD_SYNC_EXISTING')
            ->count());
        $this->assertSame('authorized', $token->fresh()->meta->state);
        $this->assertSame('bacs', $token->fresh()->meta->scheme);
        $this->assertSame('CU_SYNC', $token->fresh()->gateway_customer_reference);
    }

    public function test_replace_mandate_updates_the_existing_payment_method_in_place(): void
    {
        $this->makeTestData();

        $company_gateway = $this->makeCompanyGateway();
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $company_gateway->id;
        $token->gateway_type_id = GatewayType::DIRECT_DEBIT;
        $token->token = 'MD_REPLACED';
        $token->gateway_customer_reference = 'CU_OLD';
        $token->meta = (object) [
            'brand' => 'Old Bank',
            'last4' => '0000',
            'state' => 'pending',
        ];
        $token->save();
        $hashed_id = $token->hashed_id;
        $other_token = new ClientGatewayToken();
        $other_token->company_id = $this->company->id;
        $other_token->client_id = $this->client->id;
        $other_token->company_gateway_id = $company_gateway->id;
        $other_token->gateway_type_id = GatewayType::DIRECT_DEBIT;
        $other_token->token = 'MD_UNRELATED';
        $other_token->gateway_customer_reference = 'CU_OTHER';
        $other_token->meta = (object) ['state' => 'authorized'];
        $other_token->save();
        $service = $this->makeMandateSyncService(
            $company_gateway,
            $this->client,
            'MD_REPLACEMENT',
            'active',
        );

        $replacement = $service->replaceMandate('MD_REPLACED', 'MD_REPLACEMENT');

        $this->assertSame($token->id, $replacement->id);
        $this->assertSame($hashed_id, $replacement->hashed_id);
        $this->assertSame('MD_REPLACEMENT', $replacement->token);
        $this->assertSame('CU_SYNC', $replacement->gateway_customer_reference);
        $this->assertSame('authorized', $replacement->meta->state);
        $this->assertSame('bacs', $replacement->meta->scheme);
        $this->assertSame('Test Bank', $replacement->meta->brand);
        $this->assertSame('1234', $replacement->meta->last4);
        $this->assertSame('MD_UNRELATED', $other_token->fresh()->token);
        $this->assertFalse(ClientGatewayToken::query()
            ->where('company_gateway_id', $company_gateway->id)
            ->where('token', 'MD_REPLACED')
            ->exists());
    }

    public function test_replace_mandate_is_idempotent(): void
    {
        $this->makeTestData();

        $company_gateway = $this->makeCompanyGateway();
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $company_gateway->id;
        $token->gateway_type_id = GatewayType::DIRECT_DEBIT;
        $token->token = 'MD_RETRY_OLD';
        $token->gateway_customer_reference = 'CU_OLD';
        $token->meta = (object) ['state' => 'pending'];
        $token->save();
        $service = $this->makeMandateSyncService(
            $company_gateway,
            $this->client,
            'MD_RETRY_NEW',
            'active',
            2,
        );

        $first = $service->replaceMandate('MD_RETRY_OLD', 'MD_RETRY_NEW');
        $second = $service->replaceMandate('MD_RETRY_OLD', 'MD_RETRY_NEW');

        $this->assertSame($token->id, $first->id);
        $this->assertSame($token->id, $second->id);
        $this->assertSame(1, ClientGatewayToken::query()
            ->where('company_gateway_id', $company_gateway->id)
            ->where('token', 'MD_RETRY_NEW')
            ->count());
    }

    public function test_sync_mandate_cannot_reassign_an_existing_mandate_to_another_client(): void
    {
        $this->makeTestData();

        $company_gateway = $this->makeCompanyGateway();
        $other_client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $other_client->id;
        $token->company_gateway_id = $company_gateway->id;
        $token->gateway_type_id = GatewayType::DIRECT_DEBIT;
        $token->token = 'MD_SYNC_OWNED';
        $token->gateway_customer_reference = 'CU_OTHER';
        $token->meta = (object) ['state' => 'pending'];
        $token->save();
        $service = $this->makeMandateSyncService(
            $company_gateway,
            $this->client,
            'MD_SYNC_OWNED',
            'active',
        );

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('GoCardless mandate is already linked to another client.');

        $service->syncMandate('MD_SYNC_OWNED');
    }

    public function test_hosted_payment_page_creates_a_billing_request_flow(): void
    {
        $payload = $this->captureHostedFlowPayload(true);

        $this->assertSame('BRQ_HOSTED', data_get($payload, 'links.billing_request'));
        $this->assertTrue(data_get($payload, 'show_redirect_buttons'));
        $this->assertTrue(data_get($payload, 'show_success_redirect_button'));
        $this->assertSame('GB', data_get($payload, 'prefilled_customer.country_code'));
        $this->assertStringContainsString('/gocardless/hosted_payment_page/return/', data_get($payload, 'redirect_uri'));
        $this->assertStringContainsString('/client/invoices/', data_get($payload, 'exit_uri'));
        $this->assertHostedReturnLifetime(data_get($payload, 'redirect_uri'));
    }

    public function test_hosted_payment_method_setup_uses_the_setup_return_route(): void
    {
        $payload = $this->captureHostedFlowPayload(false);

        $this->assertSame('BRQ_HOSTED', data_get($payload, 'links.billing_request'));
        $this->assertStringContainsString('/gocardless/hosted_payment_page/setup_return/', data_get($payload, 'redirect_uri'));
        $this->assertStringContainsString('/client/payment_methods', data_get($payload, 'exit_uri'));
        $this->assertHostedReturnLifetime(data_get($payload, 'redirect_uri'));
    }

    public function test_hosted_payment_method_setup_caches_only_the_contact_key(): void
    {
        $this->makeTestData();
        $this->actingAs($this->contact, 'contact');

        $company_gateway = $this->makeCompanyGateway();
        $driver = Mockery::mock(GoCardlessPaymentDriver::class);
        $driver->company_gateway = $company_gateway;
        $driver->shouldReceive('init')->once();
        $direct_debit = new DirectDebit($driver);

        $method = new \ReflectionMethod($direct_debit, 'createHostedFlowContext');
        $context_id = $method->invoke($direct_debit, GatewayType::DIRECT_DEBIT);
        $state = cache()->get($context_id);

        $this->assertSame($this->contact->contact_key, $state['contact_key']);
        $this->assertArrayNotHasKey('contact', $state);
    }

    public function test_direct_debit_checkout_starts_the_hosted_flow_for_a_new_account(): void
    {
        $view = file_get_contents(resource_path('views/portal/ninja2020/gateways/gocardless/direct_debit/pay_livewire.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('toggle-payment-with-new-gocardless-account', $view);
        $this->assertStringContainsString('name="source" value=""', $view);
        $this->assertStringNotContainsString('go-cardless.custom-payment-page', $view);
    }

    public function test_instant_bank_pay_smooth_flow_redirects_to_the_hosted_page(): void
    {
        $view = file_get_contents(resource_path('views/portal/ninja2020/gateways/gocardless/instant_bank_pay/pay_livewire.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('window.location.assign(@js($authorisation_url))', $view);
        $this->assertStringNotContainsString('go-cardless.custom-payment-page', $view);
    }

    public function test_hosted_payment_return_rejects_an_unsigned_request(): void
    {
        $response = $this->get('/gocardless/hosted_payment_page/return/company/gateway/hash');

        $response->assertForbidden();
    }

    public function test_hosted_payment_method_setup_return_rejects_an_unsigned_request(): void
    {
        $response = $this->get('/gocardless/hosted_payment_page/setup_return/company/gateway/context');

        $response->assertForbidden();
    }

    public function test_hosted_payment_method_setup_return_rejects_a_different_contact_key(): void
    {
        $this->makeTestData();
        $this->actingAs($this->contact, 'contact');
        $company_gateway = $this->makeCompanyGateway();
        $context_id = 'test-context-' . str()->random(12);
        cache()->put($context_id, [
            'db' => $this->company->db,
            'company_gateway_id' => $company_gateway->id,
            'gateway_type_id' => GatewayType::DIRECT_DEBIT,
            'contact_key' => str()->random(40),
            'gocardless' => ['billing_request' => 'BRQ_OTHER_CONTACT'],
        ], now()->addHour());
        $url = URL::temporarySignedRoute('gocardless.hosted_payment_page.setup_return', now()->addHour(), [
            'company_key' => $this->company->company_key,
            'company_gateway_id' => $company_gateway->hashed_id,
            'context' => $context_id,
        ]);

        $this->get($url)->assertForbidden();
    }

    public function test_legacy_payment_method_response_cannot_attach_an_arbitrary_billing_request(): void
    {
        $this->makeTestData();
        $this->withoutMiddleware([ThrottleRequests::class, VerifyCsrfToken::class]);

        $company_gateway = $this->makeCompanyGateway();
        $fees_and_limits = new FeesAndLimits();
        $company_gateway->fees_and_limits = [GatewayType::DIRECT_DEBIT => $fees_and_limits];
        $company_gateway->save();

        $country = Country::query()->where('iso_3166_2', 'GB')->firstOrFail();
        $currency = Currency::query()->where('code', 'GBP')->firstOrFail();
        $settings = $this->client->settings;
        $settings->currency_id = (string) $currency->id;
        $settings->company_gateway_ids = $company_gateway->hashed_id;
        $this->client->settings = $settings;
        $this->client->country_id = $country->id;
        $this->client->save();

        $token_count = ClientGatewayToken::query()->count();
        $this->actingAs(ClientContact::query()->findOrFail($this->contact->id), 'contact');

        $response = $this->post(route('client.payment_methods.store', [
            'method' => GatewayType::DIRECT_DEBIT,
        ]), [
            'billing_request' => 'BRQ_ARBITRARY',
        ]);

        $response->assertSee(ctrans('texts.gateway_temporarily_unavailable'));
        $this->assertSame($token_count, ClientGatewayToken::query()->count());
    }

    public function test_legacy_authorization_response_fails_before_gocardless_lookup(): void
    {
        $gateway = Mockery::mock(GoCardlessClient::class);
        $gateway->shouldNotReceive('billingRequests');
        $driver = Mockery::mock(GoCardlessPaymentDriver::class)->makePartial();
        $driver->gateway = $gateway;
        $driver->shouldReceive('init')->once()->andReturnSelf();

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionCode(403);

        (new DirectDebit($driver))->authorizeResponse(Request::create('/', 'POST', [
            'billing_request' => 'BRQ_ARBITRARY',
        ]));
    }

    public function test_legacy_instant_bank_payment_callback_route_is_removed(): void
    {
        $this->assertFalse(Route::has('gocardless.ibp_redirect'));
    }

    private function assertHostedReturnLifetime(string $url): void
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertGreaterThan(now()->addDays(6)->timestamp, (int) data_get($query, 'expires'));
        $this->assertLessThanOrEqual(now()->addDays(HostedPaymentPage::FLOW_EXPIRY_DAYS)->timestamp, (int) data_get($query, 'expires'));
    }

    private function makeCompanyGateway(bool $test_mode = true): CompanyGateway
    {
        $company_gateway = new CompanyGateway();
        $company_gateway->company_id = $this->company->id;
        $company_gateway->user_id = $this->user->id;
        $company_gateway->gateway_key = 'b9886f9257f0c6ee7c302f1c74475f6c';
        $company_gateway->config = encrypt(json_encode(['accessToken' => 'fake', 'testMode' => $test_mode]));
        $company_gateway->fees_and_limits = [];
        $company_gateway->save();

        return $company_gateway;
    }

    private function createGatewayToken(
        CompanyGateway $company_gateway,
        Client $client,
        int $gateway_type_id,
        string $mandate_id,
    ): ClientGatewayToken {
        $token = new ClientGatewayToken();
        $token->company_id = $company_gateway->company_id;
        $token->client_id = $client->id;
        $token->company_gateway_id = $company_gateway->id;
        $token->gateway_type_id = $gateway_type_id;
        $token->token = $mandate_id;
        $token->meta = (object) ['state' => 'authorized'];
        $token->save();

        return $token;
    }

    private function makeMandateSyncService(
        CompanyGateway $company_gateway,
        Client $client,
        string $mandate_id,
        string $status,
        int $calls = 1,
    ): HostedPaymentPage {
        $mandate = new Mandate((object) [
            'id' => $mandate_id,
            'scheme' => 'bacs',
            'status' => $status,
            'links' => (object) [
                'customer' => 'CU_SYNC',
                'customer_bank_account' => 'BA_SYNC',
            ],
        ]);
        $bank_account = new CustomerBankAccount((object) [
            'id' => 'BA_SYNC',
            'bank_name' => 'Test Bank',
            'account_number_ending' => '1234',
        ]);
        $mandates = Mockery::mock(MandatesService::class);
        $mandates->shouldReceive('get')->times($calls)->with($mandate_id)->andReturn($mandate);
        $bank_accounts = Mockery::mock(CustomerBankAccountsService::class);
        $bank_accounts->shouldReceive('get')->times($calls)->with('BA_SYNC')->andReturn($bank_account);
        $gateway = Mockery::mock(GoCardlessClient::class);
        $gateway->shouldReceive('mandates')->times($calls)->andReturn($mandates);
        $gateway->shouldReceive('customerBankAccounts')->times($calls)->andReturn($bank_accounts);
        $driver = new GoCardlessPaymentDriver($company_gateway, $client);
        $driver->gateway = $gateway;

        return new HostedPaymentPage($driver, 'mandate-sync');
    }

    private function captureCreatePayload(
        string $currency_code,
        string $country_code,
        int $gateway_type_id,
        bool $payment_mode,
        bool $verify_bank_account = false,
    ): array {
        $captured = [];
        $billing_request = new BillingRequest((object) [
            'id' => 'BRQ123',
            'status' => 'pending',
            'actions' => [],
        ]);
        $billing_requests = Mockery::mock(BillingRequestsService::class);
        $billing_requests->shouldReceive('create')
            ->once()
            ->withArgs(function (array $request) use (&$captured): bool {
                $captured = $request['params'];

                return isset($request['headers']['Idempotency-Key']);
            })
            ->andReturn($billing_request);

        $gateway = Mockery::mock(GoCardlessClient::class);
        $gateway->shouldReceive('billingRequests')->once()->andReturn($billing_requests);

        $currency = new \App\Models\Currency();
        $currency->code = $currency_code;
        $currency->precision = 2;
        $country = new Country();
        $country->iso_3166_2 = $country_code;
        $client = Mockery::mock(Client::class)->makePartial();
        $client->client_hash = 'client-hash';
        $client->country = $country;
        $client->shouldReceive('getCurrencyCode')->andReturn($currency_code);
        $client->shouldReceive('currency')->andReturn($currency);

        $payment_hash = Mockery::mock(PaymentHash::class)->makePartial();
        $payment_hash->hash = 'payment-hash';
        $payment_hash->data = (object) [];
        $payment_hash->shouldReceive('amount_with_fee')->andReturn(10.00);
        $payment_hash->shouldReceive('invoices')->andReturn([]);
        $payment_hash->shouldReceive('withData')->zeroOrMoreTimes()->andReturnSelf();

        $company_gateway = Mockery::mock(CompanyGateway::class)->makePartial();
        $company_gateway->shouldReceive('getConfigField')->with('verifyBankAccount')->andReturn($verify_bank_account);

        $driver = Mockery::mock(GoCardlessPaymentDriver::class)->makePartial();
        $driver->gateway = $gateway;
        $driver->client = $client;
        $driver->payment_hash = $payment_hash;
        $driver->company_gateway = $company_gateway;
        $driver->shouldReceive('convertToGoCardlessAmount')->andReturn(1000);
        $driver->shouldReceive('ensurePaymentMethodAvailable')
            ->with($gateway_type_id, $payment_mode ? 10.0 : -1)
            ->andReturnSelf();

        (new HostedPaymentPage($driver, 'test-context-' . str()->random(12), $payment_mode))->create($gateway_type_id);

        return $captured;
    }

    private function captureHostedFlowPayload(bool $payment_mode): array
    {
        $this->makeTestData();

        $captured = [];
        $billing_request = new BillingRequest((object) [
            'id' => 'BRQ_HOSTED',
            'status' => 'pending',
            'actions' => [],
        ]);
        $billing_request_flow = new BillingRequestFlow((object) [
            'id' => 'BRF_HOSTED',
            'authorisation_url' => 'https://pay-sandbox.gocardless.com/billing/static/flow',
        ]);
        $billing_requests = Mockery::mock(BillingRequestsService::class);
        $billing_requests->shouldReceive('create')->once()->andReturn($billing_request);
        $billing_request_flows = Mockery::mock(BillingRequestFlowsService::class);
        $billing_request_flows->shouldReceive('create')
            ->once()
            ->withArgs(function (array $request) use (&$captured): bool {
                $captured = $request['params'];

                return isset($request['headers']['Idempotency-Key']);
            })
            ->andReturn($billing_request_flow);
        $gateway = Mockery::mock(GoCardlessClient::class);
        $gateway->shouldReceive('billingRequests')->once()->andReturn($billing_requests);
        $gateway->shouldReceive('billingRequestFlows')->once()->andReturn($billing_request_flows);

        $company_gateway = $this->makeCompanyGateway();
        $currency = new \App\Models\Currency();
        $currency->code = 'GBP';
        $currency->precision = 2;
        $country = new Country();
        $country->iso_3166_2 = 'GB';
        $client = Mockery::mock(Client::class)->makePartial();
        $client->id = $this->client->id;
        $client->client_hash = $this->client->client_hash;
        $client->country = $country;
        $client->shouldReceive('getCurrencyCode')->andReturn('GBP');
        $client->shouldReceive('currency')->andReturn($currency);

        $payment_hash = Mockery::mock(PaymentHash::class)->makePartial();
        $payment_hash->hash = 'payment-hash';
        $payment_hash->data = (object) [
            'invoices' => [(object) ['invoice_id' => $this->invoice->hashed_id]],
        ];
        $payment_hash->shouldReceive('amount_with_fee')->andReturn(10.00);
        $payment_hash->shouldReceive('invoices')->andReturn([]);
        $payment_hash->shouldReceive('withData')->zeroOrMoreTimes()->andReturnSelf();

        $driver = Mockery::mock(GoCardlessPaymentDriver::class)->makePartial();
        $driver->gateway = $gateway;
        $driver->client = $client;
        $driver->payment_hash = $payment_hash;
        $driver->company_gateway = $company_gateway;
        $driver->shouldReceive('ensurePaymentMethodAvailable')->andReturnSelf();

        $context_id = 'test-context-' . str()->random(12);

        if (! $payment_mode) {
            cache()->put($context_id, [], now()->addHour());
        }

        (new HostedPaymentPage($driver, $context_id, $payment_mode))->start(GatewayType::DIRECT_DEBIT);

        return $captured;
    }
}
