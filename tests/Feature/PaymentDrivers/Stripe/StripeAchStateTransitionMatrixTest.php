<?php

namespace Tests\Feature\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\Stripe\ACH;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentFailureWebhook;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentProcessingWebhook;
use App\PaymentDrivers\Stripe\PaymentMethodSyncService;
use App\PaymentDrivers\StripePaymentDriver;
use App\Repositories\ClientGatewayTokenRepository;
use App\Services\Invoice\AutoBillInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\Mandate;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;
use Tests\MockAccountData;
use Tests\TestCase;

class StripeAchStateTransitionMatrixTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const STRIPE_GATEWAY_KEY = 'd14dd26a37cecc30fdd65700bfb55b23';

    private CompanyGateway $companyGateway;

    protected function setUp(): void
    {
        parent::setUp();

        Model::reguard();
        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'scout.driver' => null,
            'scout.queue' => false,
            'session.driver' => 'array',
        ]);

        $this->makeTestData();

        $this->companyGateway = new CompanyGateway();
        $this->companyGateway->company_id = $this->company->id;
        $this->companyGateway->user_id = $this->user->id;
        $this->companyGateway->gateway_key = self::STRIPE_GATEWAY_KEY;
        $this->companyGateway->fees_and_limits = new \stdClass();
        $this->companyGateway->config = encrypt(json_encode([
            'apiKey' => 'sk_test_state_matrix',
            'publishableKey' => 'pk_test_state_matrix',
            'webhookSecret' => 'whsec_state_matrix',
        ]));
        $this->companyGateway->save();
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testSetupIntentSuccessAuthorizesEveryStoredAchState(
        string $prefix,
        string $initialState,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);

        $this->ach()->handleSetupIntentSucceeded($this->setupIntentEvent($token, 'succeeded'));

        $meta = $token->fresh()->meta;

        $this->assertSame('authorized', $meta->state);
        $this->assertObjectNotHasProperty('next_action', $meta);
        $this->assertObjectNotHasProperty('mandate_id', $meta);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testSetupIntentFailureOnlyExpiresUnverifiedStates(
        string $prefix,
        string $initialState,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);

        $this->ach()->handleSetupIntentFailed($this->setupIntentEvent($token, 'setup_failed'));

        $meta = $token->fresh()->meta;
        $isUnverified = in_array($initialState, ['unauthorized', 'pending'], true);

        $this->assertSame($isUnverified ? 'unauthorized' : $initialState, $meta->state);

        if ($isUnverified) {
            $this->assertObjectNotHasProperty('next_action', $meta);
        } else {
            $this->assertSame($this->verificationUrl($token), $meta->next_action);
        }
    }

    #[DataProvider('mandateTransitionProvider')]
    public function testMandateStatusTransitionMatrix(
        string $prefix,
        string $initialState,
        string $mandateStatus,
        string $expectedState,
        bool $nextActionIsCleared,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);

        $this->ach()->handleMandateUpdated($this->mandateEvent($token, $mandateStatus));

        $meta = $token->fresh()->meta;

        $this->assertSame($expectedState, $meta->state);

        if ($nextActionIsCleared) {
            $this->assertObjectNotHasProperty('next_action', $meta);
        } else {
            $this->assertSame($this->verificationUrl($token), $meta->next_action ?? null);
        }
    }

    #[DataProvider('eventSequenceProvider')]
    public function testDuplicateAndOutOfOrderEventsReachADeterministicState(
        string $prefix,
        string $initialState,
        array $events,
        string $expectedState,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);

        foreach ($events as $event) {
            $this->applyEvent($token, $event);
        }

        $this->assertSame($expectedState, $token->fresh()->meta->state);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testSetupIntentEventsCannotChangeATokenBelongingToAnotherCustomer(
        string $prefix,
        string $initialState,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);
        $event = $this->setupIntentEvent($token, 'succeeded');
        $event['data']['object']['customer'] = 'cus_another_customer';

        $this->ach()->handleSetupIntentSucceeded($event);

        $meta = $token->fresh()->meta;

        $this->assertSame($initialState, $meta->state);
        $this->assertSame($this->verificationUrl($token), $meta->next_action);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testNonAchSetupIntentEventsAreIgnored(
        string $prefix,
        string $initialState,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);
        $event = $this->setupIntentEvent($token, 'succeeded');
        $event['data']['object']['payment_method_types'] = ['card'];

        $this->ach()->handleSetupIntentSucceeded($event);

        $meta = $token->fresh()->meta;

        $this->assertSame($initialState, $meta->state);
        $this->assertSame($this->verificationUrl($token), $meta->next_action);
    }

    #[DataProvider('malformedSetupIntentProvider')]
    public function testIncompleteSetupIntentEventsAreIgnored(
        string $prefix,
        string $handler,
        array $object,
    ): void {
        $token = $this->achToken($prefix, 'unauthorized', true);

        if (($object['payment_method'] ?? null) === '__TOKEN__') {
            $object['payment_method'] = $token->token;
        }

        if (($object['customer'] ?? null) === '__CUSTOMER__') {
            $object['customer'] = $token->gateway_customer_reference;
        }

        $event = ['data' => ['object' => $object]];

        match ($handler) {
            'succeeded' => $this->ach()->handleSetupIntentSucceeded($event),
            'failed' => $this->ach()->handleSetupIntentFailed($event),
        };

        $meta = $token->fresh()->meta;

        $this->assertSame('unauthorized', $meta->state);
        $this->assertSame($this->verificationUrl($token), $meta->next_action);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testFailedSetupIntentCannotChangeATokenBelongingToAnotherCustomer(
        string $prefix,
        string $initialState,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);
        $event = $this->setupIntentEvent($token, 'setup_failed');
        $event['data']['object']['customer'] = 'cus_another_customer';

        $this->ach()->handleSetupIntentFailed($event);

        $meta = $token->fresh()->meta;

        $this->assertSame($initialState, $meta->state);
        $this->assertSame($this->verificationUrl($token), $meta->next_action);
    }

    #[DataProvider('legacySourceUpdateProvider')]
    public function testLegacySourceUpdateTransitionMatrix(
        string $initialState,
        string $sourceStatus,
        string $expectedState,
    ): void {
        $token = $this->achToken('ba', $initialState);

        $this->ach()->updateBankAccount([
            'data' => ['object' => [
                'id' => $token->token,
                'customer' => $token->gateway_customer_reference,
                'object' => 'bank_account',
                'status' => $sourceStatus,
            ]],
        ]);

        $this->assertSame($expectedState, $token->fresh()->meta->state);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testPaymentIntentProcessingAuthorizesEveryStateAndClearsVerificationMetadata(
        string $prefix,
        string $initialState,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);

        (new PaymentIntentProcessingWebhook(
            ['object' => [
                'id' => 'pi_matrix_processing',
                'payment_method' => $token->token,
            ]],
            $this->company->company_key,
            $this->companyGateway->id,
        ))->handle();

        $meta = $token->fresh()->meta;

        $this->assertSame('authorized', $meta->state);
        $this->assertObjectNotHasProperty('next_action', $meta);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testPaymentIntentFailureDoesNotChangePaymentMethodState(
        string $prefix,
        string $initialState,
    ): void {
        $token = $this->achToken($prefix, $initialState, true);

        (new PaymentIntentFailureWebhook(
            ['object' => [
                'id' => 'pi_matrix_failed',
                'payment_method' => $token->token,
                'failure_message' => 'Test failure',
            ]],
            $this->company->company_key,
            $this->companyGateway->id,
        ))->handle();

        $meta = $token->fresh()->meta;

        $this->assertSame($initialState, $meta->state);
        $this->assertSame($this->verificationUrl($token), $meta->next_action);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testOnlyAuthorizedAchTokensAreEligibleForAutoBilling(
        string $prefix,
        string $state,
    ): void {
        $token = $this->achToken($prefix, $state);
        $gateway = (new AutoBillInvoice($this->invoice, $this->company->db))->getGateway(10);

        if ($state === 'authorized') {
            $this->assertTrue($token->is($gateway));
        } else {
            $this->assertFalse($gateway);
        }
    }

    #[DataProvider('verificationCtaProvider')]
    public function testPaymentMethodShowPageActionMatrix(
        string $prefix,
        string $state,
        ?string $nextAction,
        string $expectedAction,
    ): void {
        $token = $this->achToken($prefix, $state);

        if ($nextAction !== null) {
            $meta = $token->meta;
            $meta->next_action = $nextAction;
            $token->meta = $meta;
            $token->save();
        }

        $response = $this->actingAs($this->contact, 'contact')
            ->get(route('client.payment_methods.show', $token->hashed_id));

        $expectedAction = match ($expectedAction) {
            '__ADD_BANK_ACCOUNT_ROUTE__' => route('client.payment_methods.create', [
                'method' => GatewayType::BANK_TRANSFER,
            ]),
            '__TOKEN_VERIFICATION_ROUTE__' => route('client.payment_methods.verification', [
                'payment_method' => $token->hashed_id,
                'method' => GatewayType::BANK_TRANSFER,
            ]),
            default => $expectedAction,
        };

        $response->assertOk();
        $response->assertSee($expectedAction, false);
        $this->assertNotNull($token->fresh());
    }

    #[DataProvider('prefixAndUnverifiedStateProvider')]
    public function testVerificationFlowPrioritizesNextActionForBothTokenTypes(
        string $prefix,
        string $state,
    ): void {
        $token = $this->achToken($prefix, $state, true);
        $driver = Mockery::mock(
            StripePaymentDriver::class,
            [$this->companyGateway, $this->client]
        )->makePartial();
        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldNotReceive('getStripePaymentMethod');
        $driver->shouldNotReceive('createSetupIntent');

        $response = (new ACH($driver))->verificationView($token);

        $this->assertSame($this->verificationUrl($token), $response->getTargetUrl());
        $this->assertSame($state, $token->fresh()->meta->state);
    }

    #[DataProvider('prefixProvider')]
    public function testAuthorizedVerificationViewIsIdempotent(string $prefix): void
    {
        $token = $this->achToken($prefix, 'authorized');
        $driver = $this->verificationDriver($token);
        $driver->shouldNotReceive('createSetupIntent');

        $response = (new ACH($driver))->verificationView($token);

        $this->assertTrue($response->isRedirect(route('client.payment_methods.show', $token->hashed_id)));
        $this->assertSame('authorized', $token->fresh()->meta->state);
    }

    #[DataProvider('prefixProvider')]
    public function testInactiveVerificationViewAlwaysOffersMandateRenewal(string $prefix): void
    {
        $token = $this->achToken($prefix, 'inactive');
        $driver = $this->verificationDriver($token);
        $this->expectMandateSetupIntent($driver, $token);

        $view = (new ACH($driver))->verificationView($token);

        $this->assertSame('portal.ninja2020.gateways.stripe.ach.reauthorize', $view->name());
        $this->assertSame('inactive', $token->fresh()->meta->state);
    }

    #[DataProvider('prefixAndUnverifiedStateProvider')]
    public function testDetachedUnverifiedModernPaymentMethodReturnsToReplacementCta(
        string $prefix,
        string $state,
    ): void {
        if ($prefix !== 'pm') {
            $this->addToAssertionCount(1);

            return;
        }

        $token = $this->achToken($prefix, $state);
        $driver = $this->verificationDriver($token, null);
        $driver->shouldNotReceive('createSetupIntent');

        $response = (new ACH($driver))->verificationView($token);

        $this->assertTrue($response->isRedirect(route('client.payment_methods.show', $token->hashed_id)));
        $this->assertSame($state, $token->fresh()->meta->state);
    }

    #[DataProvider('prefixAndRenewableStateProvider')]
    public function testDetachedAuthorizedOrInactiveTokenCannotContinue(
        string $prefix,
        string $state,
    ): void {
        $token = $this->achToken($prefix, $state, true);
        $driver = $this->verificationDriver($token, null);
        $driver->shouldNotReceive('createSetupIntent');

        $response = (new ACH($driver))->verificationView($token);
        $meta = $token->fresh()->meta;

        $this->assertTrue($response->isRedirect(route('client.payment_methods.show', $token->hashed_id)));
        $this->assertSame('unauthorized', $meta->state);
        $this->assertObjectNotHasProperty('next_action', $meta);
    }

    #[DataProvider('unverifiedStateProvider')]
    public function testUnverifiedLegacySourceRendersMicrodepositForm(string $state): void
    {
        $token = $this->achToken('ba', $state);
        $driver = $this->verificationDriver($token);
        $driver->shouldNotReceive('createSetupIntent');

        $view = $this->withStripeSourceStatus(
            'new',
            fn() => (new ACH($driver))->verificationView($token)
        );

        $this->assertSame('portal.ninja2020.gateways.stripe.ach.verify', $view->name());
        $this->assertSame($state, $token->fresh()->meta->state);
    }

    #[DataProvider('unverifiedStateProvider')]
    public function testExternallyVerifiedLegacySourceRequiresMandateRenewal(string $state): void
    {
        $token = $this->achToken('ba', $state);
        $driver = $this->verificationDriver($token);
        $this->expectMandateSetupIntent($driver, $token);

        $view = $this->withStripeSourceStatus(
            'verified',
            fn() => (new ACH($driver))->verificationView($token)
        );

        $this->assertSame('portal.ninja2020.gateways.stripe.ach.reauthorize', $view->name());
        $this->assertSame('inactive', $token->fresh()->meta->state);
    }

    #[DataProvider('unverifiedStateProvider')]
    public function testManualLegacyMicrodepositVerificationTransitionsToInactive(string $state): void
    {
        $token = $this->achToken('ba', $state);
        $driver = Mockery::mock(
            StripePaymentDriver::class,
            [$this->companyGateway, $this->client]
        )->makePartial();
        $driver->shouldReceive('init')->once()->andReturnSelf();
        $request = Request::create('/client/payment_method/verification', 'POST', [
            'customer' => $token->gateway_customer_reference,
            'source' => $token->token,
            'transactions' => [32, 45],
        ]);

        $response = $this->withStripeSourceStatus(
            'verified',
            fn() => (new ACH($driver))->processVerification($request, $token)
        );

        $this->assertSame('inactive', $token->fresh()->meta->state);
        $this->assertTrue($response->isRedirect(route('client.payment_methods.verification', [
            'payment_method' => $token->hashed_id,
            'method' => GatewayType::BANK_TRANSFER,
        ])));
    }

    #[DataProvider('unverifiedStateProvider')]
    public function testUnverifiedModernPaymentMethodPostOffersReplacement(string $state): void
    {
        $token = $this->achToken('pm', $state);
        $response = (new ACH(new StripePaymentDriver($this->companyGateway, $this->client)))
            ->processVerification(Request::create('/verification', 'POST'), $token);

        $this->assertTrue($response->isRedirect(route('client.payment_methods.create', [
            'method' => GatewayType::BANK_TRANSFER,
        ])));
        $this->assertSame($state, $token->fresh()->meta->state);
    }

    #[DataProvider('mandateValidationProvider')]
    public function testMandateSetupIntentValidationMatrix(
        string $prefix,
        string $scenario,
        bool $isValid,
    ): void {
        $token = $this->achToken($prefix, 'inactive');
        $driver = $this->mandateValidationDriver($token, $scenario);
        $ach = new ACH($driver);
        $request = Request::create('/client/payments/response', 'POST', [
            'setup_intent_id' => 'seti_matrix',
        ]);
        $request->setLaravelSession(app('session.store'));
        $sessionKey = $this->mandateSessionKey($token);
        $request->session()->put(
            $sessionKey,
            $scenario === 'session_mismatch' ? 'seti_another_intent' : 'seti_matrix'
        );

        $method = new ReflectionMethod(ACH::class, 'validatedMandateSetupIntent');

        if ($isValid) {
            $intent = $method->invoke($ach, $request, $token);

            $this->assertSame('seti_matrix', $intent->id);
            $this->assertFalse($request->session()->has($sessionKey));

            return;
        }

        try {
            $method->invoke($ach, $request, $token);
            $this->fail("Invalid mandate scenario [{$scenario}] was accepted for {$prefix}_.");
        } catch (PaymentFailed $exception) {
            $this->assertSame(400, $exception->getCode());
            $this->assertTrue($request->session()->has($sessionKey));
        }
    }

    #[DataProvider('prefixProvider')]
    public function testSuccessfulMandateReauthorizationClearsStaleNextAction(string $prefix): void
    {
        $token = $this->achToken($prefix, 'inactive', true);
        $driver = $this->mandateValidationDriver($token, 'valid');
        $driver->shouldReceive('syncAchPaymentMethodBillingAddress')->once()->with($token);
        $request = Request::create('/client/payment_method/verification', 'POST', [
            'setup_intent_id' => 'seti_matrix',
        ]);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put($this->mandateSessionKey($token), 'seti_matrix');

        $response = (new ACH($driver))->processVerification($request, $token);
        $meta = $token->fresh()->meta;

        $this->assertTrue($response->isRedirect(route('client.payment_methods.show', $token->hashed_id)));
        $this->assertSame('authorized', $meta->state);
        $this->assertObjectNotHasProperty('next_action', $meta);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testRemotePaymentMethodDetachmentRemovesEveryMatchingTokenState(
        string $prefix,
        string $state,
    ): void {
        $token = $this->achToken($prefix, $state);
        $service = new PaymentMethodSyncService(new ClientGatewayTokenRepository());

        $service->removePaymentMethod(
            $this->companyGateway->newCollection([$this->companyGateway]),
            $token->token,
        );

        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $token->id]);
    }

    #[DataProvider('paymentMethodUpdateProvider')]
    public function testRemotePaymentMethodUpdateRefreshesMetadataWithoutChangingState(
        string $prefix,
        string $state,
        bool $automaticallyUpdated,
    ): void {
        $token = $this->achToken($prefix, $state, true);
        $service = new PaymentMethodSyncService(new ClientGatewayTokenRepository());

        $service->updatePaymentMethod(
            $this->companyGateway->newCollection([$this->companyGateway]),
            (object) [
                'id' => $token->token,
                'type' => 'us_bank_account',
                'us_bank_account' => (object) [
                    'bank_name' => 'Updated Matrix Bank',
                    'last4' => '4321',
                ],
            ],
            $automaticallyUpdated,
        );

        $meta = $token->fresh()->meta;

        $this->assertSame($state, $meta->state);
        $this->assertSame('Updated Matrix Bank (ACH)', $meta->brand);
        $this->assertSame('4321', $meta->last4);
        $this->assertSame($this->verificationUrl($token), $meta->next_action);
    }

    #[DataProvider('prefixAndStateProvider')]
    public function testRemoteCustomerDeletionRemovesEveryMatchingTokenState(
        string $prefix,
        string $state,
    ): void {
        $token = $this->achToken($prefix, $state);
        $service = new PaymentMethodSyncService(new ClientGatewayTokenRepository());

        $service->removeCustomerPaymentMethods(
            $this->companyGateway->newCollection([$this->companyGateway]),
            $token->gateway_customer_reference,
        );

        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $token->id]);
    }

    public static function prefixAndStateProvider(): iterable
    {
        foreach (['pm', 'ba'] as $prefix) {
            foreach (['unauthorized', 'pending', 'authorized', 'inactive'] as $state) {
                yield "{$prefix}_ {$state}" => [$prefix, $state];
            }
        }
    }

    public static function prefixProvider(): iterable
    {
        yield 'pm_' => ['pm'];
        yield 'ba_' => ['ba'];
    }

    public static function malformedSetupIntentProvider(): iterable
    {
        $events = [
            'success missing payment method types' => ['succeeded', [
                'status' => 'succeeded',
                'payment_method' => '__TOKEN__',
                'customer' => '__CUSTOMER__',
            ]],
            'success missing payment method' => ['succeeded', [
                'status' => 'succeeded',
                'payment_method_types' => ['us_bank_account'],
                'customer' => '__CUSTOMER__',
            ]],
            'success missing customer' => ['succeeded', [
                'status' => 'succeeded',
                'payment_method' => '__TOKEN__',
                'payment_method_types' => ['us_bank_account'],
                'customer' => null,
            ]],
            'failure missing payment method' => ['failed', [
                'status' => 'requires_payment_method',
                'payment_method' => null,
                'payment_method_types' => ['us_bank_account'],
                'customer' => '__CUSTOMER__',
            ]],
            'failure missing customer' => ['failed', [
                'status' => 'requires_payment_method',
                'payment_method' => '__TOKEN__',
                'payment_method_types' => ['us_bank_account'],
                'customer' => null,
            ]],
            'failure for card' => ['failed', [
                'status' => 'requires_payment_method',
                'payment_method' => '__TOKEN__',
                'payment_method_types' => ['card'],
                'customer' => '__CUSTOMER__',
            ]],
        ];

        foreach (['pm', 'ba'] as $prefix) {
            foreach ($events as $name => [$handler, $object]) {
                yield "{$prefix}_ {$name}" => [$prefix, $handler, $object];
            }
        }
    }

    public static function paymentMethodUpdateProvider(): iterable
    {
        foreach (self::prefixAndStateProvider() as $name => [$prefix, $state]) {
            yield "{$name} manual update" => [$prefix, $state, false];
            yield "{$name} automatic update" => [$prefix, $state, true];
        }
    }

    public static function prefixAndUnverifiedStateProvider(): iterable
    {
        foreach (['pm', 'ba'] as $prefix) {
            foreach (['unauthorized', 'pending'] as $state) {
                yield "{$prefix}_ {$state}" => [$prefix, $state];
            }
        }
    }

    public static function prefixAndRenewableStateProvider(): iterable
    {
        foreach (['pm', 'ba'] as $prefix) {
            foreach (['authorized', 'inactive'] as $state) {
                yield "{$prefix}_ {$state}" => [$prefix, $state];
            }
        }
    }

    public static function unverifiedStateProvider(): iterable
    {
        yield 'unauthorized' => ['unauthorized'];
        yield 'pending' => ['pending'];
    }

    public static function mandateTransitionProvider(): iterable
    {
        foreach (['pm', 'ba'] as $prefix) {
            foreach (['unauthorized', 'pending', 'authorized', 'inactive'] as $initialState) {
                foreach (['active', 'pending', 'inactive'] as $mandateStatus) {
                    $expectedState = match (true) {
                        $mandateStatus === 'inactive'
                            && in_array($initialState, ['authorized', 'inactive'], true) => 'inactive',
                        $mandateStatus === 'active' && $initialState === 'inactive' => 'authorized',
                        default => $initialState,
                    };

                    yield "{$prefix}_ {$initialState} receives {$mandateStatus}" => [
                        $prefix,
                        $initialState,
                        $mandateStatus,
                        $expectedState,
                        $mandateStatus === 'active' && $initialState === 'inactive',
                    ];
                }
            }
        }
    }

    public static function eventSequenceProvider(): iterable
    {
        $sequences = [
            'failure then success' => ['unauthorized', ['setup_failed', 'setup_succeeded'], 'authorized'],
            'success then stale failure' => ['unauthorized', ['setup_succeeded', 'setup_failed'], 'authorized'],
            'inactive mandate then success' => ['authorized', ['mandate_inactive', 'setup_succeeded'], 'authorized'],
            'success then inactive mandate' => ['inactive', ['setup_succeeded', 'mandate_inactive'], 'inactive'],
            'duplicate setup success' => ['inactive', ['setup_succeeded', 'setup_succeeded'], 'authorized'],
            'duplicate inactive mandate' => ['authorized', ['mandate_inactive', 'mandate_inactive'], 'inactive'],
            'pending mandate is inert' => ['pending', ['mandate_pending', 'mandate_pending'], 'pending'],
            'active mandate does not bypass verification' => ['unauthorized', ['mandate_active'], 'unauthorized'],
            'active mandate recovers inactive token' => ['inactive', ['mandate_active'], 'authorized'],
            'inactive then active mandate' => ['authorized', ['mandate_inactive', 'mandate_active'], 'authorized'],
        ];

        foreach (['pm', 'ba'] as $prefix) {
            foreach ($sequences as $name => [$initialState, $events, $expectedState]) {
                yield "{$prefix}_ {$name}" => [$prefix, $initialState, $events, $expectedState];
            }
        }
    }

    public static function legacySourceUpdateProvider(): iterable
    {
        foreach (['unauthorized', 'pending', 'authorized', 'inactive'] as $state) {
            yield "{$state} source remains unchanged while new" => [$state, 'new', $state];
            yield "{$state} source receives verified" => [
                $state,
                'verified',
                $state === 'authorized' ? 'authorized' : 'inactive',
            ];
        }
    }

    public static function verificationCtaProvider(): iterable
    {
        $verificationUrl = fn(string $prefix): string => 'https://verify.stripe.test/' . $prefix;

        yield 'pm unauthorized without action offers replacement' => [
            'pm',
            'unauthorized',
            null,
            '__ADD_BANK_ACCOUNT_ROUTE__',
        ];
        yield 'pm unauthorized with action continues verification' => [
            'pm',
            'unauthorized',
            $verificationUrl('pm-unauthorized'),
            $verificationUrl('pm-unauthorized'),
        ];
        yield 'pm pending with action continues verification' => [
            'pm',
            'pending',
            $verificationUrl('pm-pending'),
            $verificationUrl('pm-pending'),
        ];
        yield 'ba unauthorized offers legacy verification' => [
            'ba',
            'unauthorized',
            null,
            '__TOKEN_VERIFICATION_ROUTE__',
        ];
        yield 'ba pending with action continues verification' => [
            'ba',
            'pending',
            $verificationUrl('ba-pending'),
            '__TOKEN_VERIFICATION_ROUTE__',
        ];
        yield 'pm inactive offers mandate renewal' => [
            'pm',
            'inactive',
            null,
            '__TOKEN_VERIFICATION_ROUTE__',
        ];
        yield 'ba inactive offers mandate renewal' => [
            'ba',
            'inactive',
            null,
            '__TOKEN_VERIFICATION_ROUTE__',
        ];
    }

    public static function mandateValidationProvider(): iterable
    {
        $scenarios = [
            'valid' => true,
            'session_mismatch' => false,
            'intent_incomplete' => false,
            'customer_mismatch' => false,
            'payment_method_mismatch' => false,
            'mandate_missing' => false,
            'mandate_inactive' => false,
            'mandate_payment_method_mismatch' => false,
        ];

        foreach (['pm', 'ba'] as $prefix) {
            foreach ($scenarios as $scenario => $isValid) {
                yield "{$prefix}_ {$scenario}" => [$prefix, $scenario, $isValid];
            }
        }
    }

    private function ach(): ACH
    {
        return new ACH(new StripePaymentDriver($this->companyGateway, $this->client));
    }

    private function achToken(string $prefix, string $state, bool $withNextAction = false): ClientGatewayToken
    {
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $this->companyGateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = $prefix . '_matrix_' . uniqid();
        $token->gateway_customer_reference = 'cus_matrix_' . uniqid();
        $token->meta = (object) [
            'brand' => 'State Matrix Bank',
            'last4' => '6789',
            'state' => $state,
        ];

        if ($withNextAction) {
            $meta = $token->meta;
            $meta->next_action = $this->verificationUrl($token);
            $token->meta = $meta;
        }

        $token->save();

        return $token;
    }

    private function setupIntentEvent(ClientGatewayToken $token, string $event): array
    {
        $failed = $event === 'setup_failed';

        return [
            'data' => ['object' => [
                'id' => $failed ? 'seti_matrix_failed' : 'seti_matrix_succeeded',
                'customer' => $token->gateway_customer_reference,
                'payment_method' => $failed ? null : $token->token,
                'payment_method_types' => ['us_bank_account'],
                'status' => $failed ? 'requires_payment_method' : 'succeeded',
                'mandate' => $failed ? null : 'mandate_matrix',
                'last_setup_error' => $failed
                    ? ['payment_method' => ['id' => $token->token]]
                    : null,
            ]],
        ];
    }

    private function mandateEvent(ClientGatewayToken $token, string $status): array
    {
        return [
            'data' => ['object' => [
                'id' => 'mandate_matrix_' . $status,
                'object' => 'mandate',
                'status' => $status,
                'payment_method' => $token->token,
            ]],
        ];
    }

    private function applyEvent(ClientGatewayToken $token, string $event): void
    {
        match ($event) {
            'setup_succeeded' => $this->ach()->handleSetupIntentSucceeded(
                $this->setupIntentEvent($token, 'succeeded')
            ),
            'setup_failed' => $this->ach()->handleSetupIntentFailed(
                $this->setupIntentEvent($token, 'setup_failed')
            ),
            'mandate_active' => $this->ach()->handleMandateUpdated($this->mandateEvent($token, 'active')),
            'mandate_pending' => $this->ach()->handleMandateUpdated($this->mandateEvent($token, 'pending')),
            'mandate_inactive' => $this->ach()->handleMandateUpdated($this->mandateEvent($token, 'inactive')),
        };
    }

    /** @return StripePaymentDriver&MockInterface */
    private function verificationDriver(
        ClientGatewayToken $token,
        ?string $customerId = 'attached',
    ): StripePaymentDriver {
        $driver = Mockery::mock(
            StripePaymentDriver::class,
            [$this->companyGateway, $this->client]
        )->makePartial();
        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldReceive('getStripePaymentMethod')
            ->once()
            ->with($token->token)
            ->andReturn(PaymentMethod::constructFrom([
                'id' => $token->token,
                'object' => 'payment_method',
                'type' => 'us_bank_account',
                'customer' => $customerId === 'attached'
                    ? $token->gateway_customer_reference
                    : $customerId,
            ]));
        $driver->shouldReceive('hasCompleteBillingAddress')->byDefault()->andReturn(true);
        $driver->shouldReceive('syncAchPaymentMethodBillingAddress')->byDefault();

        return $driver;
    }

    /** @param StripePaymentDriver&MockInterface $driver */
    private function expectMandateSetupIntent(
        StripePaymentDriver $driver,
        ClientGatewayToken $token,
    ): void {
        $driver->shouldReceive('createSetupIntent')
            ->once()
            ->with([
                'customer' => $token->gateway_customer_reference,
                'payment_method' => $token->token,
                'payment_method_types' => ['us_bank_account'],
                'usage' => 'off_session',
            ])
            ->andReturn(SetupIntent::constructFrom([
                'id' => 'seti_matrix_verification',
                'client_secret' => 'seti_matrix_verification_secret',
                'status' => 'requires_confirmation',
            ]));
    }

    /** @return StripePaymentDriver&MockInterface */
    private function mandateValidationDriver(
        ClientGatewayToken $token,
        string $scenario,
    ): StripePaymentDriver {
        $driver = Mockery::mock(
            StripePaymentDriver::class,
            [$this->companyGateway, $this->client]
        )->makePartial();

        if ($scenario === 'session_mismatch') {
            $driver->shouldNotReceive('getSetupIntentId');
            $driver->shouldNotReceive('getMandate');

            return $driver;
        }

        $intent = SetupIntent::constructFrom([
            'id' => 'seti_matrix',
            'status' => $scenario === 'intent_incomplete' ? 'requires_confirmation' : 'succeeded',
            'customer' => $scenario === 'customer_mismatch'
                ? 'cus_another_customer'
                : $token->gateway_customer_reference,
            'payment_method' => $scenario === 'payment_method_mismatch'
                ? 'pm_another_method'
                : $token->token,
            'mandate' => $scenario === 'mandate_missing' ? null : 'mandate_matrix',
        ]);

        $driver->shouldReceive('getSetupIntentId')
            ->once()
            ->with('seti_matrix')
            ->andReturn($intent);

        if (in_array($scenario, [
            'intent_incomplete',
            'customer_mismatch',
            'payment_method_mismatch',
            'mandate_missing',
        ], true)) {
            $driver->shouldNotReceive('getMandate');

            return $driver;
        }

        $driver->shouldReceive('getMandate')
            ->once()
            ->with('mandate_matrix')
            ->andReturn(Mandate::constructFrom([
                'id' => 'mandate_matrix',
                'status' => $scenario === 'mandate_inactive' ? 'inactive' : 'active',
                'payment_method' => $scenario === 'mandate_payment_method_mismatch'
                    ? 'pm_another_method'
                    : $token->token,
            ]));

        return $driver;
    }

    private function verificationUrl(ClientGatewayToken $token): string
    {
        return 'https://verify.stripe.test/' . $token->token;
    }

    private function withStripeSourceStatus(string $status, callable $callback): mixed
    {
        Stripe::setApiKey('sk_test_state_matrix');
        ApiRequestor::setHttpClient(new class ($status) implements ClientInterface {
            public function __construct(private readonly string $status) {}

            public function request(
                $method,
                $absUrl,
                $headers,
                $params,
                $hasFile,
                $apiMode = 'v1',
                $maxNetworkRetries = null,
            ): array {
                return [json_encode([
                    'id' => 'ba_matrix_source',
                    'object' => 'bank_account',
                    'customer' => 'cus_matrix_source',
                    'bank_name' => 'State Matrix Bank',
                    'last4' => '6789',
                    'status' => $this->status,
                ], JSON_THROW_ON_ERROR), 200, []];
            }
        });

        try {
            return $callback();
        } finally {
            ApiRequestor::setHttpClient(null);
        }
    }

    private function mandateSessionKey(ClientGatewayToken $token): string
    {
        return "stripe_ach.mandate_setup_intent.{$token->id}";
    }
}
