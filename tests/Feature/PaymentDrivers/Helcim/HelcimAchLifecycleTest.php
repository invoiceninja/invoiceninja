<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\PaymentDrivers\Helcim;

use App\Jobs\Mail\PaymentFailedMailer;
use App\Exceptions\PaymentFailed;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\ClientGatewayToken;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\Helcim\ACH;
use App\PaymentDrivers\HelcimPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

class HelcimAchLifecycleTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $gateway;

    private const WEBHOOK_SECRET = 'helcim-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->gateway = new CompanyGateway();
        $this->gateway->company_id = $this->company->id;
        $this->gateway->user_id = $this->user->id;
        $this->gateway->gateway_key = 'ca3b3f7e4be811c96a8a1f4cafe2a97f';
        $this->gateway->require_cvv = false;
        $this->gateway->require_billing_address = false;
        $this->gateway->require_shipping_address = false;
        $this->gateway->update_details = false;
        $this->gateway->config = encrypt(json_encode([
            'apiToken' => 'test-api-token',
            'webhookVerifierToken' => base64_encode(self::WEBHOOK_SECRET),
        ]));
        $this->gateway->fees_and_limits = [];
        $this->gateway->save();
    }

    public function testApprovedAchRemainsPendingUntilClearingCompletes(): void
    {
        $payment = $this->makePayment('helcim-pending', Payment::STATUS_PENDING);
        $currency = $payment->currency()->firstOrFail();
        Http::fake([
            "https://api.helcim.com/v2/ach/transactions/{$payment->transaction_reference}" => Http::sequence()
                ->push($this->transactionResponse($payment, $currency->code, 1, 0))
                ->push($this->transactionResponse($payment, $currency->code, 1, 1)),
        ]);

        $result = $this->driver()->reconcileAchPayment($payment);

        $this->assertSame('pending', $result);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status_id);

        $result = $this->driver()->reconcileAchPayment($payment);

        $this->assertSame('completed', $result);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
    }

    public function testHelcimPayApprovedAuthorizationCreatesPendingPayment(): void
    {
        Bus::fake();
        Event::fake();

        $paymentHash = $this->makePaymentHash(10);
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        $currency = $this->client->currency()->code;
        $invoiceNumber = $this->testInvoiceNumber();

        Http::fake([
            'https://api.helcim.com/v2/ach/transactions/browser-123' => Http::response([
                'transaction' => [
                    'transactionId' => 'browser-123',
                    'orderId' => 701,
                    'amount' => 10,
                    'currency' => $currency,
                    'statusAuth' => 1,
                    'statusClearing' => 0,
                ],
            ]),
            'https://api.helcim.com/v2/invoices*' => Http::response([
                'invoices' => [[
                    'invoiceId' => 701,
                    'invoiceNumber' => $invoiceNumber,
                ]],
            ]),
        ]);

        $request = $this->helcimPayRequest([
            'transactionId' => 'browser-123',
            'invoiceNumber' => $invoiceNumber,
            'statusAuth' => 1,
            'statusClearing' => 0,
        ], 'browser-secret', $this->paymentContext($paymentHash));
        Cache::put($this->paymentCheckoutSessionKey($paymentHash), [
            'checkoutToken' => 'browser-checkout-token',
            'secretToken' => 'browser-secret',
            'amount' => 10,
            'currency' => $currency,
            'invoiceNumber' => $invoiceNumber,
        ], 60);

        $this->invokeBrowserPayment(new ACH($driver), $request, $paymentHash);

        $payment = Payment::query()
            ->where('company_gateway_id', $this->gateway->id)
            ->where('transaction_reference', 'browser-123')
            ->firstOrFail();

        $this->assertSame(Payment::STATUS_PENDING, $payment->status_id);
        $this->assertNull(Cache::get($this->paymentCheckoutSessionKey($paymentHash)));
        $this->assertNull(Cache::get('helcim-ach-checkout:' . hash('sha256', 'browser-secret')));

        $this->invokeBrowserPayment(new ACH($driver), $request, $paymentHash->fresh());
        $this->assertSame(1, Payment::query()
            ->where('company_gateway_id', $this->gateway->id)
            ->where('transaction_reference', 'browser-123')
            ->count());
        $this->assertCount(1, collect(Http::recorded())->filter(
            fn (array $record): bool => $record[0]->url()
                === 'https://api.helcim.com/v2/ach/transactions/browser-123'
        ));

        $driver->payment_hash = $paymentHash->fresh();
        try {
            (new ACH($driver))->paymentData([]);
            $this->fail('A completed PaymentHash must not initialize another checkout.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('already been completed', $e->getMessage());
        }
        $this->assertCount(0, collect(Http::recorded())->filter(
            fn (array $record): bool => $record[0]->url()
                === 'https://api.helcim.com/v2/helcim-pay/initialize'
        ));
    }

    public function testHelcimPayResponseWithoutTransactionIdFailsClosed(): void
    {
        $paymentHash = $this->makePaymentHash(10);
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        $request = $this->helcimPayRequest([
            'statusAuth' => 1,
            'statusClearing' => 0,
            'bankToken' => 'not-enough-to-correlate',
        ], 'browser-secret', $this->paymentContext($paymentHash));

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('No transactionId');

        $this->invokeBrowserPayment(new ACH($driver), $request, $paymentHash);
    }

    public function testHelcimPayRejectsAOneCentAuthoritativeAmountMismatch(): void
    {
        $paymentHash = $this->makePaymentHash(10.01);
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        $currency = $this->client->currency()->code;
        $invoiceNumber = $this->testInvoiceNumber();
        Http::fake([
            'https://api.helcim.com/v2/ach/transactions/browser-one-cent-short' => Http::response([
                'transaction' => [
                    'transactionId' => 'browser-one-cent-short',
                    'orderId' => 703,
                    'amount' => 10.00,
                    'currency' => $currency,
                    'statusAuth' => 1,
                    'statusClearing' => 0,
                ],
            ]),
        ]);
        $request = $this->helcimPayRequest([
            'transactionId' => 'browser-one-cent-short',
            'invoiceNumber' => $invoiceNumber,
            'statusAuth' => 1,
            'statusClearing' => 0,
        ], 'one-cent-secret', $this->paymentContext($paymentHash));

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('amount mismatch');

        $this->invokeBrowserPayment(new ACH($driver), $request, $paymentHash);
    }

    public function testBrowserAchCannotReplayATransactionRecordedForAnotherClient(): void
    {
        $otherClient = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        ClientContact::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $otherClient->id,
            'user_id' => $this->user->id,
            'is_primary' => true,
        ]);
        Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $otherClient->id,
            'user_id' => $this->user->id,
            'company_gateway_id' => $this->gateway->id,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
            'type_id' => PaymentType::ACH,
            'transaction_reference' => 'browser-replayed',
            'status_id' => Payment::STATUS_PENDING,
            'amount' => 10,
            'currency_id' => $this->client->getSetting('currency_id'),
        ]);

        $paymentHash = $this->makePaymentHash(10);
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        $currency = $this->client->currency()->code;
        $invoiceNumber = $this->testInvoiceNumber();
        Http::fake([
            'https://api.helcim.com/v2/ach/transactions/browser-replayed' => Http::response([
                'transaction' => [
                    'transactionId' => 'browser-replayed',
                    'orderId' => 702,
                    'amount' => 10,
                    'currency' => $currency,
                    'statusAuth' => 1,
                    'statusClearing' => 0,
                ],
            ]),
            'https://api.helcim.com/v2/invoices*' => Http::response([
                'invoices' => [[
                    'invoiceId' => 702,
                    'invoiceNumber' => $invoiceNumber,
                ]],
            ]),
        ]);
        $request = $this->helcimPayRequest([
            'transactionId' => 'browser-replayed',
            'invoiceNumber' => $invoiceNumber,
            'statusAuth' => 1,
            'statusClearing' => 0,
        ], 'replay-secret', $this->paymentContext($paymentHash));

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('already been recorded');

        $this->invokeBrowserPayment(new ACH($driver), $request, $paymentHash);
    }

    public function testBrowserAchRejectsATransactionNotLinkedToItsCheckoutInvoice(): void
    {
        $paymentHash = $this->makePaymentHash(10);
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        $currency = $this->client->currency()->code;
        $invoiceNumber = $this->testInvoiceNumber();

        Http::fake([
            'https://api.helcim.com/v2/ach/transactions/browser-unbound' => Http::response([
                'transaction' => [
                    'transactionId' => 'browser-unbound',
                    'orderId' => 800,
                    'amount' => 10,
                    'currency' => $currency,
                    'statusAuth' => 1,
                    'statusClearing' => 0,
                ],
            ]),
            'https://api.helcim.com/v2/invoices*' => Http::response([
                'invoices' => [[
                    'invoiceId' => 801,
                    'invoiceNumber' => $invoiceNumber,
                ]],
            ]),
        ]);
        $request = $this->helcimPayRequest([
            'transactionId' => 'browser-unbound',
            'invoiceNumber' => $invoiceNumber,
            'statusAuth' => 1,
            'statusClearing' => 0,
        ], 'unbound-secret', $this->paymentContext($paymentHash));

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('not linked to this checkout');

        $this->invokeBrowserPayment(new ACH($driver), $request, $paymentHash);
    }

    public function testPaymentDataInitializesCheckoutWithUniqueInvoiceBinding(): void
    {
        $paymentHash = $this->makePaymentHash(10);
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        Http::fake([
            'https://api.helcim.com/v2/helcim-pay/initialize' => Http::response([
                'checkoutToken' => 'checkout-token-1',
                'secretToken' => 'secret-token-1',
            ]),
        ]);

        $ach = new ACH($driver);
        $viewData = $ach->paymentData([]);

        Http::assertNothingSent();

        $first = $ach->initializePaymentCheckout($viewData['checkout_fingerprint']);
        $second = $ach->initializePaymentCheckout($viewData['checkout_fingerprint']);

        $this->assertSame($first['checkoutToken'], $second['checkoutToken']);
        $this->assertSame($first['secretToken'], $second['secretToken']);

        try {
            $ach->paymentData(['amount_with_fee' => 10.01]);
            $this->fail('An active checkout must prevent payment detail changes.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('cannot be changed', $e->getMessage());
        }
        $this->assertSame(10.0, (float) $paymentHash->fresh()->data->amount_with_fee);

        Http::assertSent(function ($request): bool {
            $invoiceNumber = (string) data_get($request->data(), 'invoiceRequest.invoiceNumber');

            return $request->url() === 'https://api.helcim.com/v2/helcim-pay/initialize'
                && str_starts_with($invoiceNumber, 'IN-')
                && strlen($invoiceNumber) === 27
                && (float) data_get($request->data(), 'invoiceRequest.lineItems.0.total') === 10.0;
        });

        $invoiceNumbers = collect(Http::recorded())
            ->filter(fn (array $record): bool => $record[0]->url() === 'https://api.helcim.com/v2/helcim-pay/initialize')
            ->map(fn (array $record): string => (string) data_get($record[0]->data(), 'invoiceRequest.invoiceNumber'))
            ->values();
        $this->assertCount(1, $invoiceNumbers);
        $checkout = Cache::get('helcim-ach-checkout:' . hash('sha256', 'secret-token-1'));
        $this->assertIsArray($checkout);
        $this->assertSame($this->paymentContext($paymentHash), $checkout['context']);
        $this->assertSame($invoiceNumbers[0], $checkout['invoice_number']);
    }

    public function testBrowserCheckoutClaimBlocksSavedTokenWithdrawal(): void
    {
        $token = $this->makeToken((object) [
            'brand' => 'Checking',
            'last4' => '1234',
            'type' => GatewayType::BANK_TRANSFER,
            'bankAccountId' => '321',
            'customerId' => '654',
        ]);
        $paymentHash = $this->makePaymentHash(10);
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        Http::fake([
            'https://api.helcim.com/v2/helcim-pay/initialize' => Http::response([
                'checkoutToken' => 'browser-mode-token',
                'secretToken' => 'browser-mode-secret',
            ]),
        ]);

        $ach = new ACH($driver);
        $viewData = $ach->paymentData([]);
        $ach->initializePaymentCheckout($viewData['checkout_fingerprint']);

        try {
            $driver->tokenBilling($token, $paymentHash->fresh());
            $this->fail('A saved withdrawal must not run after a browser checkout is issued.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('new Helcim bank account', $e->getMessage());
        }

        $this->assertSame('browser', data_get($paymentHash->fresh()->data, 'helcim_ach_payment_mode'));
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://api.helcim.com/v2/ach/withdraw');
    }

    public function testBrowserCheckoutClaimBlocksASecondHelcimGatewayCheckout(): void
    {
        $secondGateway = $this->makeSecondGateway();
        $paymentHash = $this->makePaymentHash(10);
        $firstDriver = $this->driver();
        $firstDriver->payment_hash = $paymentHash;
        $secondDriver = new HelcimPaymentDriver($secondGateway, $this->client);
        $secondDriver->payment_hash = $paymentHash->fresh();
        $firstAch = new ACH($firstDriver);
        $secondAch = new ACH($secondDriver);
        $firstViewData = $firstAch->paymentData([]);
        $secondViewData = $secondAch->paymentData([]);
        Http::fake([
            'https://api.helcim.com/v2/helcim-pay/initialize' => Http::sequence()
                ->push([
                    'checkoutToken' => 'first-gateway-token',
                    'secretToken' => 'first-gateway-secret',
                ])
                ->push([
                    'checkoutToken' => 'second-gateway-token',
                    'secretToken' => 'second-gateway-secret',
                ]),
        ]);

        $firstAch->initializePaymentCheckout($firstViewData['checkout_fingerprint']);

        try {
            $secondAch->initializePaymentCheckout($secondViewData['checkout_fingerprint']);
            $this->fail('A second Helcim gateway must not issue a checkout for the same PaymentHash.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('another gateway configuration', $e->getMessage());
        }

        $claimed = $paymentHash->fresh();
        $this->assertSame($this->gateway->id, (int) data_get($claimed->data, 'helcim_ach_company_gateway_id'));
        $this->assertCount(1, collect(Http::recorded())->filter(
            fn (array $record): bool => $record[0]->url() === 'https://api.helcim.com/v2/helcim-pay/initialize'
        ));
    }

    public function testBrowserCheckoutClaimBlocksSavedTokenOnASecondHelcimGateway(): void
    {
        $secondGateway = $this->makeSecondGateway();
        $token = $this->makeToken((object) [
            'brand' => 'Checking',
            'last4' => '1234',
            'type' => GatewayType::BANK_TRANSFER,
            'bankAccountId' => '321',
            'customerId' => '654',
        ]);
        $token->company_gateway_id = $secondGateway->id;
        $token->save();
        $paymentHash = $this->makePaymentHash(10);
        $firstDriver = $this->driver();
        $firstDriver->payment_hash = $paymentHash;
        $firstAch = new ACH($firstDriver);
        $viewData = $firstAch->paymentData([]);
        Http::fake([
            'https://api.helcim.com/v2/helcim-pay/initialize' => Http::response([
                'checkoutToken' => 'cross-mode-token',
                'secretToken' => 'cross-mode-secret',
            ]),
        ]);
        $firstAch->initializePaymentCheckout($viewData['checkout_fingerprint']);

        try {
            (new HelcimPaymentDriver($secondGateway, $this->client))->tokenBilling($token, $paymentHash->fresh());
            $this->fail('A saved withdrawal on another Helcim gateway must be blocked.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('another gateway configuration', $e->getMessage());
        }

        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://api.helcim.com/v2/ach/withdraw');
    }

    public function testBrowserCheckoutSessionEndpointInitializesTheClaimedMode(): void
    {
        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            ThrottleRequests::class,
        ]);
        $paymentHash = $this->makePaymentHash(10);
        $paymentHash->fee_invoice_id = $this->invoice->id;
        $paymentHash->save();
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        $viewData = (new ACH($driver))->paymentData([]);
        Http::fake([
            'https://api.helcim.com/v2/helcim-pay/initialize' => Http::response([
                'checkoutToken' => 'endpoint-browser-token',
                'secretToken' => 'endpoint-browser-secret',
            ]),
        ]);

        $response = $this->actingAs($this->contact, 'contact')->postJson(
            route('client.payments.helcim_ach_session'),
            [
                'payment_hash' => $paymentHash->hash,
                'company_gateway_id' => $this->gateway->id,
                'checkout_fingerprint' => $viewData['checkout_fingerprint'],
            ]
        );

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'Unexpected redirect: ' . (string) $response->headers->get('Location')
        );
        $response->assertJson([
            'checkout_token' => 'endpoint-browser-token',
            'secret_token' => 'endpoint-browser-secret',
        ]);
        $this->assertSame('browser', data_get($paymentHash->fresh()->data, 'helcim_ach_payment_mode'));
    }

    public function testUncertainSavedTokenClaimBlocksBrowserCheckout(): void
    {
        $token = $this->makeToken((object) [
            'brand' => 'Checking',
            'last4' => '1234',
            'type' => GatewayType::BANK_TRANSFER,
            'bankAccountId' => '321',
            'customerId' => '654',
        ]);
        $paymentHash = $this->makePaymentHash(10);
        Http::fake([
            'https://api.helcim.com/v2/ach/withdraw' => Http::response(['errors' => 'Temporary failure'], 500),
        ]);

        try {
            $this->driver()->tokenBilling($token, $paymentHash);
            $this->fail('The uncertain withdrawal should throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
        }

        $driver = $this->driver();
        $driver->payment_hash = $paymentHash->fresh();
        $ach = new ACH($driver);
        $viewData = $ach->paymentData([]);

        try {
            $ach->initializePaymentCheckout($viewData['checkout_fingerprint']);
            $this->fail('A browser checkout must not start after an uncertain saved withdrawal.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('saved Helcim bank account', $e->getMessage());
        }

        $this->assertSame('saved_token', data_get($paymentHash->fresh()->data, 'helcim_ach_payment_mode'));
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://api.helcim.com/v2/helcim-pay/initialize');
    }

    public function testAuthorizationCheckoutCreatesAndBindsADeterministicHelcimCustomer(): void
    {
        $customerCode = $this->authorizationCustomerCode();
        Http::fake([
            "https://api.helcim.com/v2/customers?customerCode={$customerCode}" => Http::sequence()
                ->push(['customers' => []])
                ->push(['customers' => [[
                    'customerId' => 654,
                    'customerCode' => $customerCode,
                ]]]),
            'https://api.helcim.com/v2/customers' => Http::response([
                'customer' => [
                    'customerId' => 654,
                    'customerCode' => $customerCode,
                ],
            ]),
            'https://api.helcim.com/v2/helcim-pay/initialize' => Http::response([
                'checkoutToken' => 'authorization-checkout-token',
                'secretToken' => 'authorization-secret-token',
            ]),
        ]);

        (new ACH($this->driver()))->authorizeView([]);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.helcim.com/v2/customers'
            && $request['customerCode'] === $customerCode);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.helcim.com/v2/helcim-pay/initialize'
            && $request['customerCode'] === $customerCode);
        $checkout = Cache::get('helcim-ach-checkout:' . hash('sha256', 'authorization-secret-token'));
        $this->assertIsArray($checkout);
        $this->assertSame($customerCode, $checkout['customer_code']);
    }

    public function testBankAuthorizationFailsIfAccountIsNotOnTheBoundHelcimCustomer(): void
    {
        $currency = $this->client->currency()->code;
        $customerCode = $this->authorizationCustomerCode();
        Http::fake([
            'https://api.helcim.com/v2/ach/transactions/verify-123' => Http::response([
                'transaction' => [
                    'transactionId' => 'verify-123',
                    'amount' => 0,
                    'currency' => $currency,
                    'statusAuth' => 1,
                    'statusClearing' => 0,
                ],
            ]),
            'https://api.helcim.com/v2/customers/654/bank-accounts' => Http::response([
                'bankAccounts' => [],
            ]),
            'https://api.helcim.com/v2/customers*' => Http::response([
                'customers' => [
                    ['customerId' => 654, 'customerCode' => $customerCode],
                ],
            ]),
        ]);
        $request = $this->helcimPayRequest([
            'transactionId' => 'verify-123',
            'statusAuth' => 1,
            'bankToken' => 'bank-token-without-account-id',
            'customerCode' => $customerCode,
        ], 'browser-secret', $this->authorizationContext());

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('not linked to this client');

        (new ACH($this->driver()))->authorizeResponse($request);
    }

    public function testBankAuthorizationRejectsAVerificationFromAnotherHelcimCustomer(): void
    {
        $currency = $this->client->currency()->code;
        Http::fake([
            'https://api.helcim.com/v2/ach/transactions/verify-other-customer' => Http::response([
                'transaction' => [
                    'transactionId' => 'verify-other-customer',
                    'bankAccountId' => 999,
                    'amount' => 0,
                    'currency' => $currency,
                    'statusAuth' => 1,
                    'statusClearing' => 0,
                ],
            ]),
        ]);
        $request = $this->helcimPayRequest([
            'transactionId' => 'verify-other-customer',
            'statusAuth' => 1,
            'bankAccountId' => 999,
            'customerCode' => 'another-customer',
        ], 'other-customer-secret', $this->authorizationContext());
        Cache::put(
            'helcim-ach-checkout:' . hash('sha256', 'other-customer-secret'),
            [
                'context' => $this->authorizationContext(),
                'customer_code' => $this->authorizationCustomerCode(),
            ],
            60
        );

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('not linked to this client');

        (new ACH($this->driver()))->authorizeResponse($request);
    }

    public function testBankAuthorizationResolvesExactReusableWithdrawalReferences(): void
    {
        Bus::fake();
        Event::fake();

        $currency = $this->client->currency()->code;
        $customerCode = $this->authorizationCustomerCode();
        Http::fake([
            'https://api.helcim.com/v2/ach/transactions/verify-resolve' => Http::response([
                'transaction' => [
                    'transactionId' => 'verify-resolve',
                    'amount' => 0,
                    'currency' => $currency,
                    'statusAuth' => 1,
                    'statusClearing' => 0,
                ],
            ]),
            'https://api.helcim.com/v2/customers/654/bank-accounts' => Http::response([
                'bankAccounts' => [
                    ['bankAccountId' => 321, 'bankToken' => 'exact-bank-token'],
                    ['bankAccountId' => 999, 'bankToken' => 'other-bank-token'],
                ],
            ]),
            'https://api.helcim.com/v2/customers*' => Http::response([
                'customers' => [
                    ['customerId' => 654, 'customerCode' => $customerCode],
                ],
            ]),
        ]);
        $request = $this->helcimPayRequest([
            'transactionId' => 'verify-resolve',
            'statusAuth' => 1,
            'bankToken' => 'exact-bank-token',
            'customerCode' => $customerCode,
            'bankAccountNumber' => '1234',
        ], 'browser-secret', $this->authorizationContext());

        (new ACH($this->driver()))->authorizeResponse($request);

        $token = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->gateway->id)
            ->where('client_id', $this->client->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('321', $token->token);
        $this->assertSame('654', $token->gateway_customer_reference);
        $this->assertSame('321', (string) $token->meta->bankAccountId);
        $this->assertSame('654', (string) $token->meta->customerId);
        Http::assertSent(fn ($request): bool => $request->url()
            === "https://api.helcim.com/v2/customers?customerCode={$customerCode}");
        Http::assertSent(fn ($request): bool => $request->url()
            === 'https://api.helcim.com/v2/customers/654/bank-accounts');
        $this->assertNull(Cache::get('helcim-ach-checkout:' . hash('sha256', 'browser-secret')));

        try {
            (new ACH($this->driver()))->authorizeResponse($request);
            $this->fail('A consumed authorization response must not be replayed.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('Invalid or expired', $e->getMessage());
        }

        $this->assertSame(1, ClientGatewayToken::query()
            ->where('company_gateway_id', $this->gateway->id)
            ->where('client_id', $this->client->id)
            ->where('token', '321')
            ->count());
    }

    public function testReturnedPendingAchIsDeletedThenMarkedFailedAndIsIdempotent(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $payment = $this->makePayment('helcim-returned', Payment::STATUS_PENDING);
        $this->fakeTransaction($payment, statusAuth: 1, statusClearing: 'RETURNED');

        $result = $this->driver()->reconcileAchPayment($payment);

        $failed = Payment::withTrashed()->findOrFail($payment->id);
        $this->assertSame('failed', $result);
        $this->assertSame(Payment::STATUS_FAILED, $failed->status_id);
        $this->assertTrue($failed->is_deleted);
        $this->assertNotNull($failed->deleted_at);
        Bus::assertDispatched(PaymentFailedMailer::class, 1);

        $result = $this->driver()->reconcileAchPayment($failed);

        $this->assertSame('unchanged', $result);
        Bus::assertDispatched(PaymentFailedMailer::class, 1);
    }

    public function testReturnedCompletedAchRemainsCompletedForManualReversal(): void
    {
        Bus::fake();
        Event::fake();

        $invoice = $this->invoice->fresh();
        $client = $this->client->fresh();
        $amount = min(10.0, (float) $invoice->balance);
        $initialInvoiceBalance = (float) $invoice->balance;
        $initialInvoicePaid = (float) $invoice->paid_to_date;
        $initialClientBalance = (float) $client->balance;
        $initialClientPaid = (float) $client->paid_to_date;
        $paymentHash = PaymentHash::create([
            'hash' => 'helcim-accounting-' . uniqid(),
            'fee_total' => 0,
            'data' => [
                'amount_with_fee' => $amount,
                'invoices' => [[
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $amount,
                ]],
                'credits' => 0,
            ],
        ]);
        $driver = $this->driver();
        $driver->payment_hash = $paymentHash;
        $payment = $driver->createPayment([
            'payment_type' => PaymentType::ACH,
            'amount' => $amount,
            'transaction_reference' => 'helcim-accounting-return',
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ], Payment::STATUS_COMPLETED);

        $this->assertEqualsWithDelta($initialInvoiceBalance - $amount, (float) $invoice->fresh()->balance, 0.001);
        $this->assertEqualsWithDelta($initialInvoicePaid + $amount, (float) $invoice->fresh()->paid_to_date, 0.001);
        $this->assertEqualsWithDelta($initialClientBalance - $amount, (float) $client->fresh()->balance, 0.001);
        $this->assertEqualsWithDelta($initialClientPaid + $amount, (float) $client->fresh()->paid_to_date, 0.001);

        $paidInvoiceBalance = (float) $invoice->fresh()->balance;
        $paidInvoicePaid = (float) $invoice->fresh()->paid_to_date;
        $paidClientBalance = (float) $client->fresh()->balance;
        $paidClientPaid = (float) $client->fresh()->paid_to_date;

        $this->fakeTransaction($payment, statusAuth: 1, statusClearing: 'RETURNED');
        $result = $driver->reconcileAchPayment($payment);

        $this->assertSame('unchanged', $result);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
        $this->assertFalse($payment->fresh()->is_deleted);
        $this->assertEqualsWithDelta($paidInvoiceBalance, (float) $invoice->fresh()->balance, 0.001);
        $this->assertEqualsWithDelta($paidInvoicePaid, (float) $invoice->fresh()->paid_to_date, 0.001);
        $this->assertEqualsWithDelta($paidClientBalance, (float) $client->fresh()->balance, 0.001);
        $this->assertEqualsWithDelta($paidClientPaid, (float) $client->fresh()->paid_to_date, 0.001);
        Bus::assertNotDispatched(PaymentFailedMailer::class);
        Http::assertNothingSent();
    }

    public function testWebhookNeverFuzzyMatchesAPlaceholderPayment(): void
    {
        $payment = $this->makePayment('ach_pending_local-placeholder', Payment::STATUS_PENDING);
        Http::fake();

        $request = $this->signedWebhookRequest(
            json_encode(['id' => 'different-transaction'], JSON_THROW_ON_ERROR)
        );

        $response = $this->driver()->processWebhookRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status_id);
        Http::assertNothingSent();
    }

    public function testWebhookDoesNotReconcileACompletedAchPayment(): void
    {
        $payment = $this->makePayment('helcim-completed-terminal', Payment::STATUS_COMPLETED);
        Http::fake();

        $request = $this->signedWebhookRequest(
            json_encode(['id' => $payment->transaction_reference], JSON_THROW_ON_ERROR)
        );

        $response = $this->driver()->processWebhookRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Payment not found'], $response->getData(true));
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
        $this->assertFalse($payment->fresh()->is_deleted);
        Http::assertNothingSent();
    }

    public function testWebhookUsesHelcimsVersionedBase64SignatureContract(): void
    {
        $body = json_encode(['id' => 'unknown-transaction'], JSON_THROW_ON_ERROR);
        $webhookId = 'msg_123';
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac(
            'sha256',
            "{$webhookId}.{$timestamp}.{$body}",
            self::WEBHOOK_SECRET,
            true
        ));

        $valid = $this->webhookRequest($body, $webhookId, $timestamp, "v1,{$signature}");
        $invalid = $this->webhookRequest($body, $webhookId, $timestamp, 'v1,invalid');

        $this->assertSame(200, $this->driver()->processWebhookRequest($valid)->getStatusCode());
        $this->assertSame(401, $this->driver()->processWebhookRequest($invalid)->getStatusCode());
    }

    public function testWebhookIsRejectedWhenVerificationIsNotConfigured(): void
    {
        $this->gateway->setConfigField('webhookVerifierToken', '');
        $request = Request::create(
            '/helcim/webhook',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => 'untrusted'], JSON_THROW_ON_ERROR)
        );

        $this->assertSame(401, $this->driver()->processWebhookRequest($request)->getStatusCode());
    }

    public function testSavedTokenBillingUsesAchWithdrawAndKeepsApprovedPaymentPending(): void
    {
        Bus::fake();
        Event::fake();

        $token = $this->makeToken((object) [
            'brand' => 'Checking',
            'last4' => '1234',
            'type' => GatewayType::BANK_TRANSFER,
            'bankAccountId' => '321',
            'customerId' => '654',
        ]);
        $paymentHash = $this->makePaymentHash(10);

        Http::fake([
            'https://api.helcim.com/v2/ach/withdraw' => Http::response([
                'transactionId' => 'withdraw-123',
                'statusAuth' => 1,
                'statusClearing' => 0,
            ]),
        ]);

        $payment = $this->driver()->tokenBilling($token, $paymentHash);
        $retriedPayment = $this->driver()->tokenBilling($token, $paymentHash->fresh());

        $this->assertSame('withdraw-123', $payment->transaction_reference);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status_id);
        $this->assertSame($payment->id, $retriedPayment->id);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://api.helcim.com/v2/ach/withdraw'
                && $request['bankAccountId'] === 321
                && $request['customerId'] === 654
                && $request['currencyId'] === 2;
        });
        $withdrawals = collect(Http::recorded())
            ->filter(fn (array $record): bool => $record[0]->url() === 'https://api.helcim.com/v2/ach/withdraw')
            ->values();

        $this->assertCount(1, $withdrawals);
        $this->assertSame(32, strlen($withdrawals->first()[0]->header('idempotency-key')[0]));
    }

    public function testSavedTokenBillingFailsClosedAfterSafeIdempotencyWindow(): void
    {
        $token = $this->makeToken((object) [
            'brand' => 'Checking',
            'last4' => '1234',
            'type' => GatewayType::BANK_TRANSFER,
            'bankAccountId' => '321',
            'customerId' => '654',
        ]);
        $paymentHash = $this->makePaymentHash(10)
            ->withData('helcim_ach_withdrawal_attempted_at', now()->subMinutes(6)->toIso8601String());
        Http::fake();

        try {
            $this->driver()->tokenBilling($token, $paymentHash);
            $this->fail('An uncertain ACH withdrawal must not be submitted again.');
        } catch (PaymentFailed $e) {
            $this->assertStringContainsString('outside the safe idempotency window', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function testDefinitiveWithdrawalRejectionDoesNotPoisonACorrectedRetry(): void
    {
        Bus::fake();
        Event::fake();

        $token = $this->makeToken((object) [
            'brand' => 'Checking',
            'last4' => '1234',
            'type' => GatewayType::BANK_TRANSFER,
            'bankAccountId' => '321',
            'customerId' => '654',
        ]);
        $paymentHash = $this->makePaymentHash(10);
        Http::fake([
            'https://api.helcim.com/v2/ach/withdraw' => Http::sequence()
                ->push(['errors' => 'Invalid payload'], 400)
                ->push([
                    'transactionId' => 'withdraw-after-rejection',
                    'statusAuth' => 1,
                    'statusClearing' => 0,
                ]),
        ]);

        try {
            $this->driver()->tokenBilling($token, $paymentHash);
            $this->fail('The rejected withdrawal should throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
        }

        $releasedPaymentHash = $paymentHash->fresh();
        $this->assertNull(data_get($releasedPaymentHash->data, 'helcim_ach_withdrawal_attempted_at'));
        $this->assertNull(data_get($releasedPaymentHash->data, 'helcim_ach_payment_mode'));
        $this->travel(6)->minutes();

        $payment = $this->driver()->tokenBilling($token, $paymentHash->fresh());

        $this->assertSame('withdraw-after-rejection', $payment->transaction_reference);
        $this->travelBack();
    }

    public function testLegacySavedTokenFailsWithReauthorizationMessage(): void
    {
        $token = $this->makeToken((object) [
            'brand' => 'Checking',
            'last4' => '1234',
            'type' => GatewayType::BANK_TRANSFER,
        ]);

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionMessage('authorize the bank account again');

        $this->driver()->tokenBilling($token, $this->makePaymentHash(10));
    }

    private function makePayment(string $reference, int $status): Payment
    {
        return Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_gateway_id' => $this->gateway->id,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
            'type_id' => PaymentType::ACH,
            'transaction_reference' => $reference,
            'status_id' => $status,
            'amount' => 10,
            'currency_id' => $this->client->getSetting('currency_id'),
        ]);
    }

    private function makeToken(object $meta): ClientGatewayToken
    {
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $this->gateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = 'legacy-bank-token';
        $token->gateway_customer_reference = '654';
        $token->meta = $meta;
        $token->save();

        return $token;
    }

    private function makeSecondGateway(): CompanyGateway
    {
        $gateway = $this->gateway->replicate();
        $gateway->save();

        return $gateway;
    }

    private function makePaymentHash(float $amount): PaymentHash
    {
        return PaymentHash::create([
            'hash' => 'helcim-hash-' . uniqid(),
            'fee_total' => 0,
            'data' => [
                'amount_with_fee' => $amount,
                'invoices' => [],
                'credits' => 0,
            ],
        ]);
    }

    private function fakeTransaction(Payment $payment, int|string $statusAuth, int|string $statusClearing): void
    {
        $currency = $payment->currency()->firstOrFail();

        Http::fake([
            "https://api.helcim.com/v2/ach/transactions/{$payment->transaction_reference}" => Http::response([
                ...$this->transactionResponse($payment, $currency->code, $statusAuth, $statusClearing),
            ]),
        ]);
    }

    private function transactionResponse(
        Payment $payment,
        string $currency,
        int|string $statusAuth,
        int|string $statusClearing
    ): array {
        return [
            'transaction' => [
                'transactionId' => $payment->transaction_reference,
                'amount' => (float) $payment->amount,
                'currency' => $currency,
                'statusAuth' => $statusAuth,
                'statusClearing' => $statusClearing,
            ],
        ];
    }

    private function driver(): HelcimPaymentDriver
    {
        return new HelcimPaymentDriver($this->gateway->fresh(), $this->client);
    }

    private function webhookRequest(string $body, string $id, string $timestamp, string $signature): Request
    {
        return Request::create('/helcim/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $id,
            'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => $signature,
        ], $body);
    }

    private function signedWebhookRequest(string $body): Request
    {
        $id = 'msg_' . uniqid();
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac(
            'sha256',
            "{$id}.{$timestamp}.{$body}",
            self::WEBHOOK_SECRET,
            true
        ));

        return $this->webhookRequest($body, $id, $timestamp, "v1,{$signature}");
    }

    private function invokeBrowserPayment(ACH $ach, Request $request, PaymentHash $paymentHash): mixed
    {
        $method = new \ReflectionMethod($ach, 'processHelcimPayACHPayment');
        $method->setAccessible(true);

        return $method->invoke($ach, $request, $paymentHash);
    }

    private function helcimPayRequest(array $data, string $secretToken, string $context): Request
    {
        $checkout = ['context' => $context];
        if (isset($data['invoiceNumber'])) {
            $checkout['invoice_number'] = $data['invoiceNumber'];
        }
        if (isset($data['customerCode'])) {
            $checkout['customer_code'] = $data['customerCode'];
        }
        Cache::put('helcim-ach-checkout:' . hash('sha256', $secretToken), $checkout, 60);
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);

        return new Request([
            'transaction_data' => $encoded,
            'transaction_hash' => hash('sha256', $encoded . $secretToken),
            'secret_token' => $secretToken,
        ]);
    }

    private function authorizationContext(): string
    {
        return "authorization:{$this->gateway->id}:{$this->client->id}";
    }

    private function paymentContext(PaymentHash $paymentHash): string
    {
        return "payment:{$this->gateway->id}:{$this->client->id}:{$paymentHash->id}";
    }

    private function authorizationCustomerCode(): string
    {
        return 'IN-' . substr(hash(
            'sha256',
            "helcim-ach-customer:{$this->gateway->id}:{$this->client->id}"
        ), 0, 24);
    }

    private function testInvoiceNumber(): string
    {
        return 'IN-' . substr(hash('sha256', uniqid('', true)), 0, 24);
    }

    private function paymentCheckoutSessionKey(PaymentHash $paymentHash): string
    {
        return 'helcim-ach-payment-session:'
            . $this->gateway->id
            . ':'
            . hash('sha256', $paymentHash->hash);
    }
}
