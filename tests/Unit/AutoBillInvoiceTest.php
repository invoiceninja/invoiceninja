<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Exceptions\PaymentFailed;
use App\PaymentDrivers\Stripe\ACH;
use App\PaymentDrivers\Stripe\BankAccountEligibility;
use App\PaymentDrivers\StripePaymentDriver;
use App\Services\Invoice\AutoBillInvoice;
use App\Livewire\Flow2\ProcessPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\Customer;
use Stripe\HttpClient\ClientInterface;
use Stripe\PaymentMethod;
use stdClass;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Services\Invoice\AutoBillInvoice
 */
class AutoBillInvoiceTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const STRIPE_GATEWAY_KEY = 'd14dd26a37cecc30fdd65700bfb55b23';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function testAutoBillFunctionality()
    {
        $this->assertEquals($this->client->balance, 10);
        $this->assertEquals($this->client->paid_to_date, 0);
        $this->assertEquals($this->client->credit_balance, 10);

        $this->invoice->service()->markSent()->autoBill();

        $this->assertNotNull($this->invoice->payments());
        $this->assertEquals(0, $this->invoice->payments()->sum('payments.amount'));

        $this->assertEquals(10, $this->invoice->payments()->get()->sum('pivot.amount'));

        $this->assertEquals($this->client->fresh()->balance, 0);
        $this->assertEquals($this->client->fresh()->paid_to_date, 10);
        $this->assertEquals($this->client->fresh()->credit_balance, 0);
    }

    public function testInactiveStripeAchTokenIsExcludedWithoutFeesAndLimits(): void
    {
        $token = $this->stripeAchToken('inactive');

        $this->assertFalse($this->autoBillService()->getGateway(10));
        $this->assertSame(0, (int) $token->fresh()->is_deleted);
    }

    public function testInactiveStripeAchTokenIsExcludedWithFeesAndLimits(): void
    {
        $this->stripeAchToken('inactive', true);

        $this->assertFalse($this->autoBillService()->getGateway(10));
    }

    public function testAuthorizedStripeAchTokenRemainsEligibleForAutoBilling(): void
    {
        $token = $this->stripeAchToken('authorized');
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->never())->method('request');
        ApiRequestor::setHttpClient($http);

        $this->assertTrue($token->is($this->autoBillService()->getGateway(10)));
    }

    public function testUnverifiedDefaultTokenIsExcludedInFavorOfValidAlternative(): void
    {
        $invalid_token = $this->tokenWithVerificationUrl();
        $invalid_token->is_default = true;
        $invalid_token->save();
        $valid_token = $this->stripeAchToken('authorized');
        $this->mockBankEligibility();

        $this->assertTrue($valid_token->is($this->autoBillService()->getGateway(10)));
        $this->assertSame(1, (int) $invalid_token->fresh()->is_default);
        $this->assertSame('authorized', $invalid_token->fresh()->meta->state);
        $this->assertObjectHasProperty('next_action', $invalid_token->fresh()->meta);
    }

    public function testNoEligibleTokenReturnsFalse(): void
    {
        $this->tokenWithVerificationUrl();
        $this->mockBankEligibility();

        $this->assertFalse($this->autoBillService()->getGateway(10));
    }

    #[DataProvider('verifiedStates')]
    public function testCompletedVerificationClearsStaleUrlAndKeepsTokenEligible(string $state): void
    {
        $token = $this->tokenWithVerificationUrl($state);
        $this->mockBankEligibility('succeeded');

        $this->assertTrue($token->is($this->autoBillService()->getGateway(10)));
        $this->assertSame('authorized', $token->fresh()->meta->state);
        $this->assertObjectNotHasProperty('next_action', $token->fresh()->meta);
        $this->assertSame('6789', $token->fresh()->meta->last4);
    }

    public static function verifiedStates(): array
    {
        return [['authorized'], ['unauthorized']];
    }

    #[DataProvider('processingStates')]
    public function testPaymentVerificationIsIndependentOfSettlement(string $payment_status, bool $eligible): void
    {
        $token = $this->tokenWithVerificationUrl();
        $this->mockBankEligibility('requires_action', 'active', false, $payment_status);
        $selected_token = $this->autoBillService()->getGateway(10);

        if ($eligible) {
            $this->assertTrue($token->is($selected_token));
            $this->assertObjectNotHasProperty('next_action', $token->fresh()->meta);
        } else {
            $this->assertFalse($selected_token);
            $this->assertObjectHasProperty('next_action', $token->fresh()->meta);
        }
    }

    public static function processingStates(): array
    {
        return [['processing', true], ['succeeded', true], ['requires_action', false]];
    }

    public function testImportEligibilityStillRequiresSettledPaymentEvidence(): void
    {
        $this->mockBankEligibility(payment_status: 'processing');
        $customer = Customer::constructFrom([
            'object' => 'customer', 'id' => 'cus_auto_bill_test', 'livemode' => false,
        ]);
        $payment_method = PaymentMethod::constructFrom([
            'object' => 'payment_method', 'id' => 'pm_auto_bill_test', 'customer' => $customer->id,
            'livemode' => false, 'type' => 'us_bank_account', 'us_bank_account' => [],
        ]);

        $this->assertNull((new BankAccountEligibility($customer, ['api_key' => 'sk_test_auto_bill']))->resolve($payment_method));
    }

    #[DataProvider('invalidMandates')]
    public function testInvalidMandateOrBlockedBankIsExcluded(string $mandate_status, bool $blocked): void
    {
        $this->tokenWithVerificationUrl();
        $this->mockBankEligibility('succeeded', $mandate_status, $blocked);

        $this->assertFalse($this->autoBillService()->getGateway(10));
    }

    public static function invalidMandates(): array
    {
        return [['inactive', false], ['pending', false], ['active', true]];
    }

    #[DataProvider('invalidBankEvidence')]
    public function testInvalidOwnershipOrMandateEvidenceIsExcluded(string $resource, array $overrides): void
    {
        $this->tokenWithVerificationUrl();
        $this->mockBankEligibility('succeeded', overrides: [$resource => $overrides]);

        $this->assertFalse($this->autoBillService()->getGateway(10));
    }

    public static function invalidBankEvidence(): array
    {
        return [
            ['payment_methods/pm_auto_bill_test', ['customer' => null]],
            ['payment_methods/pm_auto_bill_test', ['customer' => 'cus_other']],
            ['payment_methods/pm_auto_bill_test', ['livemode' => true]],
            ['mandates/mandate_test', ['payment_method' => 'pm_other']],
            ['mandates/mandate_test', ['type' => 'single_use']],
            ['mandates/mandate_test', ['on_behalf_of' => 'acct_other']],
        ];
    }

    public function testStripeLookupFailureStopsSelectionWithoutChangingMetadata(): void
    {
        $token = $this->tokenWithVerificationUrl();
        $this->stripeAchToken('authorized');
        $original_meta = $token->meta;
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willThrowException(new \RuntimeException('Stripe unavailable'));
        ApiRequestor::setHttpClient($http);

        try {
            $this->autoBillService()->getGateway(10);
            $this->fail('A lookup failure must stop selection.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Stripe unavailable', $e->getMessage());
        }

        $this->assertEquals($original_meta, $token->fresh()->meta);
    }

    #[DataProvider('missingStripeResources')]
    public function testMissingStripeResourceIsExcludedInFavorOfValidAlternative(string $resource): void
    {
        $this->tokenWithVerificationUrl();
        $valid_token = $this->stripeAchToken('authorized');
        $this->mockBankEligibility('succeeded', overrides: [$resource => [
            'error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such resource'],
        ]]);

        $this->assertTrue($valid_token->is($this->autoBillService()->getGateway(10)));
    }

    public static function missingStripeResources(): array
    {
        return [['customers/cus_auto_bill_test'], ['payment_methods/pm_auto_bill_test'], ['mandates/mandate_test']];
    }

    public function testDirectBillingCannotCreatePaymentIntentForUnverifiedToken(): void
    {
        $token = $this->tokenWithVerificationUrl();
        $this->mockBankEligibility();
        $driver = $this->getMockBuilder(StripePaymentDriver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createPaymentIntent'])
            ->getMock();
        $driver->company_gateway = $token->gateway;
        $driver->client = $this->client;
        $driver->expects($this->never())->method('createPaymentIntent');

        $this->expectException(PaymentFailed::class);
        (new ACH($driver))->paymentIntentTokenBilling(10, 'Test payment', $token, false);
    }

    #[DataProvider('invalidPortalTokens')]
    public function testPortalSubmissionRedirectsInvalidTokenBeforeAddressUpdate(string $state, ?string $customer_id): void
    {
        $token = $this->tokenWithVerificationUrl($state);
        $this->mockBankEligibility(overrides: ['payment_methods/pm_auto_bill_test' => ['customer' => $customer_id]]);
        $driver = $this->portalDriver($token);
        $driver->payment_hash = $this->portalPaymentHash();
        $driver->expects($this->never())->method('syncAchPaymentMethodBillingAddress');
        $driver->expects($this->never())->method('createPaymentIntent');

        $response = (new ACH($driver))->paymentResponse(Request::create('/client/payments/response', 'POST', [
            'source' => $token->hashed_id,
            'amount' => 10,
            'payment_method_id' => GatewayType::BANK_TRANSFER,
            'company_gateway_id' => $token->company_gateway_id,
            'customer' => $token->gateway_customer_reference,
            'currency' => 'USD',
        ]));

        $this->assertSame($token->meta->next_action, $response->getTargetUrl());
    }

    public static function invalidPortalTokens(): array
    {
        return [
            ['unauthorized', null],
            ['authorized', null],
            ['pending', 'cus_auto_bill_test'],
            ['unauthorized', 'cus_auto_bill_test'],
            ['authorized', 'cus_auto_bill_test'],
        ];
    }

    public function testPortalChoicesExcludeInvalidTokenAndKeepValidAlternative(): void
    {
        $invalid_token = $this->tokenWithVerificationUrl('unauthorized');
        $valid_token = $this->stripeAchToken('authorized');
        $this->mockBankEligibility(overrides: ['payment_methods/pm_auto_bill_test' => ['customer' => null]]);
        $driver = $this->portalDriver($invalid_token);
        $driver->expects($this->never())->method('syncAchPaymentMethodBillingAddress');
        $driver->expects($this->never())->method('createPaymentIntent');

        $data = (new ACH($driver))->paymentData([
            'tokens' => collect([$invalid_token, $valid_token]),
            'total' => ['amount_with_fee' => 10],
        ]);

        $this->assertCount(1, $data['tokens']);
        $this->assertTrue($valid_token->is($data['tokens'][0]));
        $this->assertFalse($data['client_secret']);
        $this->assertObjectHasProperty('next_action', $invalid_token->fresh()->meta);
    }

    #[DataProvider('invalidPortalTokens')]
    public function testSingleUnverifiedTokenRedirectsWithoutStartingNewPayment(string $state, ?string $customer_id): void
    {
        $token = $this->tokenWithVerificationUrl($state);
        $this->mockBankEligibility(overrides: ['payment_methods/pm_auto_bill_test' => ['customer' => $customer_id]]);
        $driver = $this->portalDriver($token);
        $driver->expects($this->never())->method('syncAchPaymentMethodBillingAddress');
        $driver->expects($this->never())->method('findOrCreateCustomer');
        $driver->expects($this->never())->method('createPaymentIntent');

        $response = (new ACH($driver))->paymentView([
            'tokens' => [$token], 'total' => ['amount_with_fee' => 10],
        ]);

        $this->assertSame($token->meta->next_action, $response->getTargetUrl());
    }

    public function testPortalReconcilesStaleUrlBeforeSynchronizingAddress(): void
    {
        $token = $this->tokenWithVerificationUrl('unauthorized');
        $this->mockBankEligibility('succeeded');
        $driver = $this->portalDriver($token);
        $driver->payment_hash = $this->portalPaymentHash();
        $driver->expects($this->once())->method('syncAchPaymentMethodBillingAddress')
            ->with($this->callback(function (ClientGatewayToken $source): bool {
                return $source->meta->state === 'authorized' && !isset($source->meta->next_action);
            }));
        $driver->expects($this->once())->method('createPaymentIntent')->willReturn(null);

        $this->assertFalse((new ACH($driver))->paymentResponse(Request::create('/client/payments/response', 'POST', [
            'source' => $token->hashed_id, 'amount' => 10,
        ])));
        $this->assertObjectNotHasProperty('next_action', $token->fresh()->meta);
    }

    public function testPortalRejectsInvalidRenewalBeforeSynchronizingAddress(): void
    {
        $token = $this->tokenWithVerificationUrl('unauthorized');
        $driver = $this->portalDriver($token);
        $driver->payment_hash = $this->portalPaymentHash();
        $driver->expects($this->never())->method('syncAchPaymentMethodBillingAddress');
        $driver->expects($this->never())->method('createPaymentIntent');
        $request = Request::create('/client/payments/response', 'POST', [
            'source' => $token->hashed_id, 'setup_intent_id' => 'seti_unexpected', 'amount' => 10,
        ]);
        $request->setLaravelSession(app('session.store'));

        $this->expectException(PaymentFailed::class);
        (new ACH($driver))->paymentResponse($request);
    }

    #[DataProvider('livewireRedirectSettings')]
    public function testLivewireRedirectsSingleUnverifiedTokenWithoutRenderingCheckout(string $state, bool $render_on_redirect, ?string $customer_id): void
    {
        $this->actingAs($this->contact, 'contact');
        $this->withoutVite();
        config(['cache.default' => 'array', 'livewire.render_on_redirect' => $render_on_redirect]);
        $token = $this->tokenWithVerificationUrl($state);
        $this->mockBankEligibility(overrides: ['payment_methods/pm_auto_bill_test' => ['customer' => $customer_id]]);
        $invitation = $this->invoice->invitations()->first();
        Cache::put($invitation->key, [
            'db' => $this->company->db,
            'contact' => $this->contact,
            'company_gateway_id' => $token->company_gateway_id,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
            'payable_invoices' => [['invoice_id' => $this->invoice->hashed_id, 'amount' => 10]],
        ], now()->addHour());

        Livewire::test(ProcessPayment::class, ['_key' => $invitation->key])
            ->assertRedirect($token->meta->next_action)
            ->assertDontSee('id="new-bank"', false);
    }

    public static function livewireRedirectSettings(): array
    {
        return [
            ['unauthorized', false, null],
            ['unauthorized', true, null],
            ['authorized', false, null],
            ['authorized', true, null],
            ['pending', false, 'cus_auto_bill_test'],
            ['pending', true, 'cus_auto_bill_test'],
        ];
    }

    #[DataProvider('nonBillableStates')]
    public function testUnverifiedTokenWithoutRecoveryUrlCannotStartCollection(string $state): void
    {
        $token = $this->stripeAchToken($state);
        $driver = $this->portalDriver($token);
        $driver->expects($this->never())->method('findOrCreateCustomer');
        $driver->expects($this->never())->method('createPaymentIntent');
        $this->expectException(PaymentFailed::class);

        (new ACH($driver))->paymentData(['tokens' => [$token], 'total' => ['amount_with_fee' => 10]]);
    }

    public function testMultipleRejectedTokensCannotStartCollectionOrChooseArbitraryUrl(): void
    {
        $first_token = $this->tokenWithVerificationUrl('unauthorized');
        $second_token = $this->tokenWithVerificationUrl('pending');
        $this->mockBankEligibility(overrides: ['payment_methods/pm_auto_bill_test' => ['customer' => null]]);
        $driver = $this->portalDriver($first_token);
        $driver->expects($this->never())->method('findOrCreateCustomer');
        $driver->expects($this->never())->method('createPaymentIntent');
        $this->expectException(PaymentFailed::class);

        (new ACH($driver))->paymentData(['tokens' => [$first_token, $second_token], 'total' => ['amount_with_fee' => 10]]);
    }

    public function testGenuinelyEmptyTokenListRetainsNewBankCollection(): void
    {
        $token = $this->stripeAchToken('authorized');
        $driver = $this->portalDriver($token);
        $driver->expects($this->never())->method('syncAchPaymentMethodBillingAddress');
        $driver->expects($this->once())->method('createPaymentIntent')
            ->with($this->callback(fn(array $data): bool => !isset($data['payment_method']) && !isset($data['confirm'])))
            ->willReturn(\Stripe\PaymentIntent::constructFrom(['client_secret' => 'pi_new_bank_secret']));

        $data = (new ACH($driver))->paymentData(['tokens' => [], 'total' => ['amount_with_fee' => 10]]);

        $this->assertSame('pi_new_bank_secret', $data['client_secret']);
    }

    public function testCheckoutLookupFailureCannotRedirectOrStartCollection(): void
    {
        $token = $this->tokenWithVerificationUrl();
        $driver = $this->portalDriver($token);
        $driver->expects($this->never())->method('findOrCreateCustomer');
        $driver->expects($this->never())->method('createPaymentIntent');
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willThrowException(new \RuntimeException('Stripe unavailable'));
        ApiRequestor::setHttpClient($http);
        $this->expectException(\RuntimeException::class);

        (new ACH($driver))->paymentData(['tokens' => [$token], 'total' => ['amount_with_fee' => 10]]);
    }

    #[DataProvider('verifiedStates')]
    public function testCheckoutValidatedStaleUrlDoesNotRedirect(string $state): void
    {
        $token = $this->tokenWithVerificationUrl($state);
        $this->mockBankEligibility('succeeded');
        $driver = $this->portalDriver($token);
        $driver->expects($this->never())->method('createPaymentIntent');

        $data = (new ACH($driver))->paymentData(['tokens' => [$token], 'total' => ['amount_with_fee' => 10]]);

        $this->assertCount(1, $data['tokens']);
        $this->assertArrayNotHasKey('ach_redirect_url', $data);
        $this->assertObjectNotHasProperty('next_action', $token->fresh()->meta);
    }

    #[DataProvider('nonBillableStates')]
    public function testFinalBillingGuardRejectsUnverifiedStateWithoutUrl(string $state): void
    {
        $token = $this->stripeAchToken($state);
        $driver = $this->portalDriver($token);
        $driver->expects($this->never())->method('createPaymentIntent');
        $this->expectException(PaymentFailed::class);

        (new ACH($driver))->paymentIntentTokenBilling(10, 'Test payment', $token, false);
    }

    public static function nonBillableStates(): array
    {
        return [['unauthorized'], ['pending']];
    }

    private function portalDriver(ClientGatewayToken $token): StripePaymentDriver
    {
        $driver = $this->getMockBuilder(StripePaymentDriver::class)
            ->setConstructorArgs([$token->gateway, $this->client])
            ->onlyMethods(['syncAchPaymentMethodBillingAddress', 'createPaymentIntent', 'findOrCreateCustomer', 'getDescription', 'getStatementDescriptor'])
            ->getMock();
        $driver->method('findOrCreateCustomer')->willReturn(Customer::constructFrom(['id' => $token->gateway_customer_reference]));
        $driver->method('getDescription')->willReturn('Test ACH payment');
        $driver->method('getStatementDescriptor')->willReturn('Test ACH payment');
        $driver->payment_hash = (object) ['hash' => 'portal_payment_test'];

        return $driver;
    }

    private function portalPaymentHash(): PaymentHash
    {
        $payment_hash = new PaymentHash();
        $payment_hash->hash = 'portal_payment_test';
        $payment_hash->fee_invoice_id = $this->invoice->id;
        $payment_hash->fee_total = 0;
        $payment_hash->data = ['invoices' => [['invoice_id' => $this->invoice->hashed_id, 'amount' => 10]]];
        $payment_hash->save();

        return $payment_hash;
    }

    private function tokenWithVerificationUrl(string $state = 'authorized'): ClientGatewayToken
    {
        $token = $this->stripeAchToken($state);
        $meta = $token->meta;
        $meta->next_action = 'https://payments.stripe.com/microdeposit/test';
        $token->meta = $meta;
        $token->save();

        return $token;
    }

    private function mockBankEligibility(
        string $setup_status = 'requires_action',
        string $mandate_status = 'active',
        bool $blocked = false,
        ?string $payment_status = null,
        array $overrides = [],
    ): void {
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturnCallback(function ($method, $url, $headers, $params) use ($setup_status, $mandate_status, $blocked, $payment_status, $overrides) {
            // Eligibility checks must never attempt a debit.
            $this->assertSame('get', strtolower($method));
            $customer_id = 'cus_auto_bill_test';
            $method_id = 'pm_auto_bill_test';
            $data = match (parse_url($url, PHP_URL_PATH)) {
                '/v1/customers/'.$customer_id => [
                    'object' => 'customer', 'id' => $customer_id, 'livemode' => false,
                ],
                '/v1/payment_methods/'.$method_id => [
                    'object' => 'payment_method', 'id' => $method_id, 'customer' => $customer_id,
                    'livemode' => false, 'type' => 'us_bank_account',
                    'us_bank_account' => ['status_details' => $blocked ? ['blocked' => ['reason' => 'debit_not_authorized']] : []],
                ],
                '/v1/setup_intents' => ['object' => 'list', 'has_more' => false, 'data' => [[
                    'object' => 'setup_intent', 'id' => 'seti_test', 'customer' => $customer_id,
                    'payment_method' => $method_id, 'status' => $setup_status, 'usage' => 'off_session',
                    'livemode' => false, 'payment_method_types' => ['us_bank_account'], 'mandate' => 'mandate_test',
                ]]],
                '/v1/charges' => ['object' => 'list', 'has_more' => false, 'data' => $payment_status === null ? [] : [[
                    'object' => 'charge', 'id' => 'ch_test', 'customer' => $customer_id,
                    'payment_method' => $method_id, 'status' => 'pending', 'paid' => true, 'livemode' => false,
                    'payment_intent' => 'pi_test',
                    'payment_method_details' => ['type' => 'us_bank_account', 'us_bank_account' => ['mandate' => 'mandate_test']],
                ]]],
                '/v1/payment_intents/pi_test' => [
                    'object' => 'payment_intent', 'id' => 'pi_test', 'customer' => $customer_id,
                    'payment_method' => $method_id, 'status' => $payment_status, 'livemode' => false,
                    'setup_future_usage' => 'off_session', 'latest_charge' => 'ch_test',
                ],
                '/v1/mandates/mandate_test' => [
                    'object' => 'mandate', 'id' => 'mandate_test', 'status' => $mandate_status,
                    'type' => 'multi_use', 'payment_method' => $method_id, 'livemode' => false,
                ],
                default => throw new \LogicException('Unexpected Stripe request: '.$url),
            };

            if (str_ends_with($url, '/setup_intents') || str_ends_with($url, '/charges')) {
                $this->assertSame($customer_id, $params['customer']);
            }

            $resource = substr(parse_url($url, PHP_URL_PATH), strlen('/v1/'));
            $data = array_replace($data, $overrides[$resource] ?? []);

            return [json_encode($data), isset($data['error']) ? 404 : 200, []];
        });
        ApiRequestor::setHttpClient($http);
    }

    private function autoBillService(): AutoBillInvoice
    {
        return new AutoBillInvoice($this->invoice, $this->company->db);
    }

    private function stripeAchToken(string $state, bool $withFeesAndLimits = false): ClientGatewayToken
    {
        $feesAndLimits = new stdClass();

        if ($withFeesAndLimits) {
            $feesAndLimits->{GatewayType::BANK_TRANSFER} = (object) [
                'min_limit' => -1,
                'max_limit' => -1,
            ];
        }

        $companyGateway = new CompanyGateway();
        $companyGateway->company_id = $this->company->id;
        $companyGateway->user_id = $this->user->id;
        $companyGateway->gateway_key = self::STRIPE_GATEWAY_KEY;
        $companyGateway->config = encrypt(json_encode([
            'apiKey' => 'sk_test_auto_bill',
            'publishableKey' => 'pk_test_auto_bill',
        ]));
        $companyGateway->fees_and_limits = $feesAndLimits;
        $companyGateway->save();

        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $companyGateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = 'pm_auto_bill_test';
        $token->gateway_customer_reference = 'cus_auto_bill_test';
        $token->meta = (object) [
            'state' => $state,
            'last4' => '6789',
        ];
        $token->save();

        return $token;
    }
}
