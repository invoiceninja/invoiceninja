<?php

namespace Tests\Feature\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\PaymentMethod\VerifyPaymentMethodRequest;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\PaymentDrivers\Stripe\ACH;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use Stripe\Customer;
use Stripe\Mandate;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Tests\MockAccountData;
use Tests\TestCase;

class StripeAchMandateReauthorizationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const STRIPE_GATEWAY_KEY = 'd14dd26a37cecc30fdd65700bfb55b23';

    private CompanyGateway $company_gateway;

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

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = self::STRIPE_GATEWAY_KEY;
        $this->company_gateway->config = encrypt(json_encode([
            'apiKey' => 'sk_test_mandate',
            'publishableKey' => 'pk_test_mandate',
        ]));
        $this->company_gateway->save();
    }

    public function testInactivePaymentMethodRendersMandateReauthorizationView(): void
    {
        $token = $this->achToken();
        $driver = $this->mockStripeDriver();

        $driver->shouldReceive('createSetupIntent')
            ->once()
            ->with([
                'customer' => $token->gateway_customer_reference,
                'payment_method' => $token->token,
                'payment_method_types' => ['us_bank_account'],
                'usage' => 'off_session',
            ])
            ->andReturn(SetupIntent::constructFrom([
                'id' => 'seti_reauthorize',
                'client_secret' => 'seti_reauthorize_secret_test',
                'status' => 'requires_confirmation',
            ]));

        $view = (new ACH($driver))->verificationView($token);

        $this->assertSame('portal.ninja2020.gateways.stripe.ach.reauthorize', $view->name());
        $this->assertSame('seti_reauthorize_secret_test', $view->getData()['client_secret']);
        $this->assertTrue($token->is($view->getData()['token']));
        $this->assertSame('seti_reauthorize', session()->get($this->mandateSessionKey($token)));
    }

    public function testInactiveLegacyBankSourceRendersTheSameMandateReauthorizationView(): void
    {
        $token = $this->achToken('inactive', 'ba_mandate_test');
        $driver = $this->mockStripeDriver(true, $token->token);

        $driver->shouldReceive('createSetupIntent')
            ->once()
            ->with([
                'customer' => $token->gateway_customer_reference,
                'payment_method' => $token->token,
                'payment_method_types' => ['us_bank_account'],
                'usage' => 'off_session',
            ])
            ->andReturn(SetupIntent::constructFrom([
                'id' => 'seti_reauthorize_ba',
                'client_secret' => 'seti_reauthorize_ba_secret_test',
                'status' => 'requires_confirmation',
            ]));

        $view = (new ACH($driver))->verificationView($token);

        $this->assertSame('portal.ninja2020.gateways.stripe.ach.reauthorize', $view->name());
        $this->assertSame('seti_reauthorize_ba_secret_test', $view->getData()['client_secret']);
        $this->assertSame('seti_reauthorize_ba', session()->get($this->mandateSessionKey($token)));
    }

    public function testPendingMicrodepositVerificationUsesHostedUrlBeforeAttachmentCheck(): void
    {
        $token = $this->achToken('unauthorized');
        $meta = $token->meta;
        $meta->next_action = 'https://verify.stripe.com/pending';
        $token->meta = $meta;
        $token->save();

        $driver = $this->mockStripeDriver(false);
        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldNotReceive('getStripePaymentMethod');
        $driver->shouldNotReceive('createSetupIntent');

        $response = (new ACH($driver))->verificationView($token);

        $this->assertSame('https://verify.stripe.com/pending', $response->getTargetUrl());
        $this->assertSame('unauthorized', $token->fresh()->meta->state);
    }

    public function testDetachedInactivePaymentMethodCannotEnterMandateReauthorization(): void
    {
        $token = $this->achToken();
        $driver = $this->mockStripeDriver(true, $token->token, null);
        $driver->shouldNotReceive('createSetupIntent');

        $response = (new ACH($driver))->verificationView($token);

        $this->assertTrue($response->isRedirect(route('client.payment_methods.show', $token->hashed_id)));
        $this->assertSame('unauthorized', $token->fresh()->meta->state);
    }

    public function testSuccessfulSetupIntentReactivatesThePaymentMethod(): void
    {
        $token = $this->achToken();
        $driver = $this->mockStripeDriver(false);

        $driver->shouldReceive('getSetupIntentId')
            ->once()
            ->with('seti_reauthorize')
            ->andReturn($this->setupIntent($token));
        $driver->shouldReceive('getMandate')
            ->once()
            ->with('mandate_reauthorize')
            ->andReturn($this->activeMandate($token));

        session()->put($this->mandateSessionKey($token), 'seti_reauthorize');

        $response = (new ACH($driver))->processVerification(
            $this->requestWithSession('/payment-method/verification', [
                'setup_intent_id' => 'seti_reauthorize',
            ]),
            $token
        );

        $this->assertSame('authorized', $token->fresh()->meta->state);
        $this->assertObjectNotHasProperty('mandate_id', $token->fresh()->meta);
        $this->assertTrue($response->isRedirect(route('client.payment_methods.show', $token->hashed_id)));
        $this->assertFalse(session()->has($this->mandateSessionKey($token)));
    }

    public function testSetupIntentForAnotherCustomerDoesNotReactivateThePaymentMethod(): void
    {
        $token = $this->achToken();
        $driver = $this->mockStripeDriver(false);

        $driver->shouldReceive('getSetupIntentId')
            ->once()
            ->with('seti_reauthorize')
            ->andReturn($this->setupIntent($token, 'cus_another_customer'));

        session()->put($this->mandateSessionKey($token), 'seti_reauthorize');

        try {
            (new ACH($driver))->processVerification(
                $this->requestWithSession('/payment-method/verification', [
                    'setup_intent_id' => 'seti_reauthorize',
                ]),
                $token
            );

            $this->fail('A SetupIntent belonging to another customer was accepted.');
        } catch (PaymentFailed $exception) {
            $this->assertSame(400, $exception->getCode());
        }

        $this->assertSame('inactive', $token->fresh()->meta->state);
    }

    public function testIncompleteSetupIntentDoesNotReactivateThePaymentMethod(): void
    {
        $token = $this->achToken();
        $driver = $this->mockStripeDriver(false);
        $setup_intent = $this->setupIntent($token);
        $setup_intent->status = 'requires_confirmation';

        $driver->shouldReceive('getSetupIntentId')
            ->once()
            ->with('seti_reauthorize')
            ->andReturn($setup_intent);

        session()->put($this->mandateSessionKey($token), 'seti_reauthorize');

        try {
            (new ACH($driver))->processVerification(
                $this->requestWithSession('/payment-method/verification', [
                    'setup_intent_id' => 'seti_reauthorize',
                ]),
                $token
            );

            $this->fail('An incomplete SetupIntent was accepted.');
        } catch (PaymentFailed $exception) {
            $this->assertSame(400, $exception->getCode());
        }

        $this->assertSame('inactive', $token->fresh()->meta->state);
    }

    public function testGetRequestDoesNotRequireASetupIntent(): void
    {
        $request = $this->verificationRequest('GET', $this->achToken());

        $this->assertSame([], $request->rules());
    }

    public function testInactivePaymentMethodPostRequiresASetupIntent(): void
    {
        $request = $this->verificationRequest('POST', $this->achToken());

        $this->assertSame(
            ['required', 'string', 'starts_with:seti_'],
            $request->rules()['setup_intent_id']
        );
    }

    public function testInactivePaymentMethodShowPageLinksToMandateReauthorization(): void
    {
        $token = $this->achToken();

        $response = $this->actingAs($this->contact, 'contact')
            ->get(route('client.payment_methods.show', $token->hashed_id));

        $response->assertOk();
        $response->assertSee(ctrans('texts.ach_authorization_required'));
        $response->assertSee(route('client.payment_methods.verification', [
            'payment_method' => $token->hashed_id,
            'method' => GatewayType::BANK_TRANSFER,
        ]), false);
    }

    public function testUnauthorizedPaymentMethodWithoutNextActionLinksToBankAccountReplacement(): void
    {
        $token = $this->achToken('unauthorized');

        $response = $this->actingAs($this->contact, 'contact')
            ->get(route('client.payment_methods.show', $token->hashed_id));

        $response->assertOk();
        $response->assertSee(ctrans('texts.unable_to_verify_payment_method'));
        $response->assertSee(route('client.payment_methods.create', [
            'method' => GatewayType::BANK_TRANSFER,
        ]), false);
        $this->assertNotNull($token->fresh());
    }

    public function testClientPresentPaymentCreatesMandateSetupIntentForInactiveToken(): void
    {
        $token = $this->achToken();
        $driver = $this->mockStripeDriver(false);

        $driver->shouldReceive('findOrCreateCustomer')
            ->once()
            ->andReturn(Customer::constructFrom(['id' => $token->gateway_customer_reference]));
        $driver->shouldReceive('getDescription')->once()->with(false)->andReturn('Test payment');
        $driver->shouldReceive('createSetupIntent')
            ->once()
            ->with([
                'customer' => $token->gateway_customer_reference,
                'payment_method_types' => ['us_bank_account'],
                'usage' => 'off_session',
            ])
            ->andReturn(SetupIntent::constructFrom([
                'id' => 'seti_payment_reauthorization',
                'client_secret' => 'seti_payment_reauthorization_secret_test',
            ]));

        $data = (new ACH($driver))->paymentData([
            'tokens' => collect([$token]),
            'total' => ['amount_with_fee' => 10],
        ]);

        $this->assertSame('seti_payment_reauthorization_secret_test', $data['mandate_client_secret']);
        $this->assertFalse($data['client_secret']);
        $this->assertSame('seti_payment_reauthorization', session()->get($this->mandateSessionKey($token)));
    }

    public function testClientPresentPaymentDoesNotCreateMandateSetupIntentForAuthorizedToken(): void
    {
        $token = $this->achToken('authorized');
        $driver = $this->mockStripeDriver(false);

        $driver->shouldReceive('findOrCreateCustomer')
            ->once()
            ->andReturn(Customer::constructFrom(['id' => $token->gateway_customer_reference]));
        $driver->shouldReceive('getDescription')->once()->with(false)->andReturn('Test payment');
        $driver->shouldNotReceive('createSetupIntent');

        $data = (new ACH($driver))->paymentData([
            'tokens' => collect([$token]),
            'total' => ['amount_with_fee' => 10],
        ]);

        $this->assertFalse($data['mandate_client_secret']);
        $this->assertFalse($data['client_secret']);
    }

    public function testReauthorizedMandateIsPassedToPaymentIntent(): void
    {
        $token = $this->achToken();
        $driver = $this->mockStripeDriver(false);
        $driver->payment_hash = (object) ['hash' => 'mandate_payment_hash'];
        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldReceive('getStatementDescriptor')->once()->andReturn('Test payment');
        $driver->shouldReceive('createPaymentIntent')
            ->once()
            ->with(Mockery::on(function (array $data) use ($token): bool {
                return $data['payment_method'] === $token->token
                    && $data['customer'] === $token->gateway_customer_reference
                    && $data['mandate'] === 'mandate_reauthorize';
            }))
            ->andReturn(null);

        $response = (new ACH($driver))->paymentIntentTokenBilling(
            10,
            'Test payment',
            $token,
            true,
            'mandate_reauthorize',
        );

        $this->assertFalse($response);
    }

    public function testPreviouslySucceededSetupIntentCannotBeReplayed(): void
    {
        $token = $this->achToken();
        $driver = $this->mockStripeDriver(false);

        $driver->shouldNotReceive('getSetupIntentId');
        session()->put($this->mandateSessionKey($token), 'seti_fresh');

        try {
            (new ACH($driver))->processVerification(
                $this->requestWithSession('/payment-method/verification', [
                    'setup_intent_id' => 'seti_previously_succeeded',
                ]),
                $token
            );

            $this->fail('A SetupIntent from an earlier authorization was accepted.');
        } catch (PaymentFailed $exception) {
            $this->assertSame(400, $exception->getCode());
        }

        $this->assertSame('inactive', $token->fresh()->meta->state);
    }

    public function testInactiveTokenCannotBypassMandateCollectionDuringClientPresentPayment(): void
    {
        $token = $this->achToken();
        $payment_hash = new PaymentHash();
        $payment_hash->hash = 'inactive_mandate_payment_hash';
        $payment_hash->fee_invoice_id = $this->invoice->id;
        $payment_hash->fee_total = 0;
        $payment_hash->data = [
            'invoices' => [[
                'invoice_id' => $this->invoice->id,
                'invoice_number' => $this->invoice->number,
                'amount' => 10,
            ]],
        ];
        $payment_hash->save();

        $driver = $this->mockStripeDriver(false);
        $driver->payment_hash = $payment_hash;
        $driver->shouldReceive('init')->once()->andReturnSelf();
        $driver->shouldReceive('getDescription')->once()->with(false)->andReturn('Test payment');
        $driver->shouldNotReceive('createPaymentIntent');

        $request = Request::create('/client/payments/response', 'POST', [
            'source' => $token->hashed_id,
            'payment_method_id' => GatewayType::BANK_TRANSFER,
            'company_gateway_id' => $this->company_gateway->id,
            'amount' => 10,
            'currency' => 'USD',
            'customer' => $token->gateway_customer_reference,
            'payment_hash' => $payment_hash->hash,
        ]);

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionCode(400);

        (new ACH($driver))->paymentResponse($request);
    }

    public function testClientPresentPaymentUsesMandateFromCompletedSetupIntent(): void
    {
        $token = $this->achToken();
        $meta = $token->meta;
        $meta->next_action = 'https://verify.stripe.com/stale';
        $token->meta = $meta;
        $token->save();
        $payment_hash = new PaymentHash();
        $payment_hash->hash = 'completed_mandate_payment_hash';
        $payment_hash->fee_invoice_id = $this->invoice->id;
        $payment_hash->fee_total = 0;
        $payment_hash->data = [
            'invoices' => [[
                'invoice_id' => $this->invoice->id,
                'invoice_number' => $this->invoice->number,
                'amount' => 10,
            ]],
        ];
        $payment_hash->save();

        $driver = $this->mockStripeDriver(false);
        $driver->payment_hash = $payment_hash;
        $driver->shouldReceive('init')->twice()->andReturnSelf();
        $driver->shouldReceive('getDescription')->once()->with(false)->andReturn('Test payment');
        $driver->shouldReceive('getStatementDescriptor')->once()->andReturn('Test payment');
        $driver->shouldReceive('getSetupIntentId')
            ->once()
            ->with('seti_reauthorize')
            ->andReturn($this->setupIntent($token));
        $driver->shouldReceive('getMandate')
            ->once()
            ->with('mandate_reauthorize')
            ->andReturn($this->activeMandate($token));
        $driver->shouldReceive('createPaymentIntent')
            ->once()
            ->with(Mockery::on(fn(array $data): bool => ($data['mandate'] ?? null) === 'mandate_reauthorize'))
            ->andReturn(null);

        session()->put($this->mandateSessionKey($token), 'seti_reauthorize');

        $request = $this->requestWithSession('/client/payments/response', [
            'source' => $token->hashed_id,
            'setup_intent_id' => 'seti_reauthorize',
            'payment_method_id' => GatewayType::BANK_TRANSFER,
            'company_gateway_id' => $this->company_gateway->id,
            'amount' => 10,
            'currency' => 'USD',
            'customer' => $token->gateway_customer_reference,
            'payment_hash' => $payment_hash->hash,
        ]);

        $response = (new ACH($driver))->paymentResponse($request);

        $this->assertFalse($response);
        $meta = $token->fresh()->meta;
        $this->assertSame('authorized', $meta->state);
        $this->assertObjectNotHasProperty('next_action', $meta);
    }

    private function achToken(string $state = 'inactive', string $paymentMethodId = 'pm_mandate_test'): ClientGatewayToken
    {
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $this->company_gateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = $paymentMethodId;
        $token->gateway_customer_reference = 'cus_mandate_test';
        $token->meta = (object) [
            'brand' => 'Test Bank',
            'last4' => '6789',
            'state' => $state,
        ];
        $token->save();

        return $token;
    }

    /** @return StripePaymentDriver&MockInterface */
    private function mockStripeDriver(
        bool $includePaymentMethod = true,
        string $paymentMethodId = 'pm_mandate_test',
        ?string $customerId = 'cus_mandate_test',
    ): StripePaymentDriver
    {
        $driver = Mockery::mock(
            StripePaymentDriver::class,
            [$this->company_gateway, $this->client]
        )->makePartial();

        $driver->shouldReceive('hasCompleteBillingAddress')
            ->byDefault()
            ->andReturn(true);
        $driver->shouldReceive('syncAchPaymentMethodBillingAddress')
            ->byDefault();

        if ($includePaymentMethod) {
            $driver->shouldReceive('init')->once()->andReturnSelf();
            $driver->shouldReceive('getStripePaymentMethod')
                ->once()
                ->with($paymentMethodId)
                ->andReturn(PaymentMethod::constructFrom([
                    'id' => $paymentMethodId,
                    'customer' => $customerId,
                ]));
        }

        return $driver;
    }

    private function setupIntent(ClientGatewayToken $token, ?string $customer = null): SetupIntent
    {
        return SetupIntent::constructFrom([
            'id' => 'seti_reauthorize',
            'status' => 'succeeded',
            'customer' => $customer ?? $token->gateway_customer_reference,
            'payment_method' => $token->token,
            'mandate' => 'mandate_reauthorize',
        ]);
    }

    private function activeMandate(ClientGatewayToken $token): Mandate
    {
        return Mandate::constructFrom([
            'id' => 'mandate_reauthorize',
            'status' => 'active',
            'payment_method' => $token->token,
        ]);
    }

    private function verificationRequest(string $method, ClientGatewayToken $token): VerifyPaymentMethodRequest
    {
        return VerifyPaymentMethodRequest::create('/payment-method/verification', $method, [
            'payment_method' => $token,
        ]);
    }

    private function requestWithSession(string $uri, array $data): Request
    {
        $request = Request::create($uri, 'POST', $data);
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    private function mandateSessionKey(ClientGatewayToken $token): string
    {
        return "stripe_ach.mandate_setup_intent.{$token->id}";
    }

}
