<?php

namespace Tests\Unit\PaymentDrivers\Stripe;

use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\Stripe\PaymentMethodSyncService;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class StripePaymentDriverWebhookTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    #[DataProvider('paymentMethodEvents')]
    public function testPaymentMethodEventsArePassedToSyncService(
        string $eventType,
        string $objectId,
        string $method,
        bool $automaticallyUpdated
    ): void {
        $gateway = $this->makeStripeGateway();
        $syncService = Mockery::mock(PaymentMethodSyncService::class);
        $syncService->shouldReceive($method)
            ->once()
            ->withArgs(function (...$arguments) use ($gateway, $objectId, $automaticallyUpdated): bool {
                if (
                    !$arguments[0] instanceof Collection
                    || !$arguments[0]->contains(fn(CompanyGateway $companyGateway): bool => $companyGateway->is($gateway))
                ) {
                    return false;
                }

                if (is_string($arguments[1])) {
                    return $arguments[1] === $objectId;
                }

                return data_get($arguments[1], 'id') === $objectId
                    && ($arguments[2] ?? false) === $automaticallyUpdated;
            });
        $this->app->instance(PaymentMethodSyncService::class, $syncService);

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest($eventType, $objectId)
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public static function paymentMethodEvents(): array
    {
        return [
            'detached' => ['payment_method.detached', 'pm_detached', 'removePaymentMethod', false],
            'updated' => ['payment_method.updated', 'pm_updated', 'updatePaymentMethod', false],
            'automatically updated' => ['payment_method.automatically_updated', 'pm_automatic', 'updatePaymentMethod', true],
            'customer deleted' => ['customer.deleted', 'cus_deleted', 'removeCustomerPaymentMethods', false],
        ];
    }

    #[DataProvider('achPaymentMethodTokens')]
    public function testInactiveMandateUpdateDisablesAchToken(string $paymentMethodId): void
    {
        $gateway = $this->makeStripeGateway();
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $gateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = $paymentMethodId;
        $token->gateway_customer_reference = 'cus_mandate_webhook';
        $token->meta = (object) ['state' => 'authorized'];
        $token->save();

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest('mandate.updated', 'mandate_inactive', [
                'status' => 'inactive',
                'payment_method' => $paymentMethodId,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('inactive', $token->fresh()->meta->state);
        $this->assertSame(0, (int) $token->fresh()->is_deleted);
    }

    public static function achPaymentMethodTokens(): array
    {
        return [
            'PaymentMethod' => ['pm_mandate_webhook'],
            'legacy bank source' => ['ba_mandate_webhook'],
        ];
    }

    #[DataProvider('achPaymentMethodTokens')]
    public function testActiveMandateUpdateReauthorizesInactiveAchToken(string $paymentMethodId): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeAchToken($gateway, $paymentMethodId, (object) [
            'state' => 'inactive',
            'next_action' => 'https://verify.stripe.com/stale',
        ]);

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest('mandate.updated', 'mandate_active', [
                'status' => 'active',
                'payment_method' => $token->token,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $meta = $token->fresh()->meta;
        $this->assertSame('authorized', $meta->state);
        $this->assertObjectNotHasProperty('next_action', $meta);
    }

    #[DataProvider('achPaymentMethodTokens')]
    public function testMandateUpdateCannotChangeTokenBelongingToAnotherGateway(string $paymentMethodId): void
    {
        $receivingGateway = $this->makeStripeGateway();
        $tokenGateway = $this->makeStripeGateway();
        $token = $this->makeAchToken($tokenGateway, $paymentMethodId, (object) [
            'state' => 'inactive',
        ]);

        $response = (new StripePaymentDriver($receivingGateway))->processWebhookRequest(
            $this->signedRequest('mandate.updated', 'mandate_active', [
                'status' => 'active',
                'payment_method' => $token->token,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('inactive', $token->fresh()->meta->state);
    }

    public function testMandateUpdateCannotChangeNonAchToken(): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeAchToken($gateway, 'pm_non_ach_mandate', (object) [
            'state' => 'inactive',
        ]);
        $token->gateway_type_id = GatewayType::CREDIT_CARD;
        $token->save();

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest('mandate.updated', 'mandate_active', [
                'status' => 'active',
                'payment_method' => $token->token,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('inactive', $token->fresh()->meta->state);
    }

    public function testActiveMandateDoesNotAuthorizeUnverifiedPaymentMethod(): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeAchToken($gateway, 'pm_pending_verification', (object) [
            'state' => 'unauthorized',
            'next_action' => 'https://verify.stripe.com/test',
        ]);

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest('mandate.updated', 'mandate_active', [
                'status' => 'active',
                'payment_method' => $token->token,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('unauthorized', $token->fresh()->meta->state);
        $this->assertSame('https://verify.stripe.com/test', $token->fresh()->meta->next_action);
        $this->assertObjectNotHasProperty('mandate_id', $token->fresh()->meta);
    }

    #[DataProvider('achPaymentMethodTokens')]
    public function testInactiveMandateDoesNotAlterPendingVerification(string $paymentMethodId): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeAchToken($gateway, $paymentMethodId, (object) [
            'state' => 'pending',
            'next_action' => 'https://verify.stripe.com/pending',
        ]);

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest('mandate.updated', 'mandate_inactive', [
                'status' => 'inactive',
                'payment_method' => $token->token,
            ])
        );

        $meta = $token->fresh()->meta;

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pending', $meta->state);
        $this->assertSame('https://verify.stripe.com/pending', $meta->next_action);
    }

    #[DataProvider('ignoredMandateStatuses')]
    public function testUnsupportedMandateStatusDoesNotAlterToken(string $status): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeAchToken($gateway, 'pm_unsupported_mandate_status', (object) [
            'state' => 'authorized',
        ]);

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest('mandate.updated', 'mandate_unsupported', [
                'status' => $status,
                'payment_method' => $token->token,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('authorized', $token->fresh()->meta->state);
    }

    public static function ignoredMandateStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'semantic alias is not accepted' => ['revoked'],
            'status matching is case-sensitive' => ['ACTIVE'],
            'empty status' => [''],
        ];
    }

    public function testSetupIntentSuccessIsTheAuthoritativeVerificationTransition(): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeAchToken($gateway, 'pm_setup_succeeded', (object) [
            'state' => 'unauthorized',
            'next_action' => 'https://verify.stripe.com/test',
        ]);

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest('setup_intent.succeeded', 'seti_setup_succeeded', [
                'customer' => $token->gateway_customer_reference,
                'payment_method' => $token->token,
                'payment_method_types' => ['us_bank_account'],
                'status' => 'succeeded',
                'mandate' => 'mandate_setup_succeeded',
            ])
        );

        $meta = $token->fresh()->meta;

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('authorized', $meta->state);
        $this->assertObjectNotHasProperty('mandate_id', $meta);
        $this->assertObjectNotHasProperty('next_action', $meta);
    }

    public function testSetupIntentFailureUsesLastErrorPaymentMethodAndClearsExpiredUrl(): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeAchToken($gateway, 'pm_setup_failed', (object) [
            'state' => 'unauthorized',
            'next_action' => 'https://verify.stripe.com/expired',
        ]);

        $response = (new StripePaymentDriver($gateway))->processWebhookRequest(
            $this->signedRequest('setup_intent.setup_failed', 'seti_setup_failed', [
                'customer' => $token->gateway_customer_reference,
                'payment_method' => null,
                'payment_method_types' => ['us_bank_account'],
                'status' => 'requires_payment_method',
                'last_setup_error' => [
                    'payment_method' => ['id' => $token->token],
                ],
            ])
        );

        $meta = $token->fresh()->meta;

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('unauthorized', $meta->state);
        $this->assertObjectNotHasProperty('next_action', $meta);
    }

    private function makeStripeGateway(): CompanyGateway
    {
        $gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $gateway->accepted_credit_cards = 0;
        $gateway->fees_and_limits = new \stdClass();
        $gateway->setConfig([
            'webhookSecret' => 'whsec_standard_test',
        ]);
        $gateway->save();

        return $gateway;
    }

    private function makeAchToken(CompanyGateway $gateway, string $paymentMethodId, object $meta): ClientGatewayToken
    {
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $gateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = $paymentMethodId;
        $token->gateway_customer_reference = 'cus_mandate_webhook';
        $token->meta = $meta;
        $token->save();

        return $token;
    }

    private function signedRequest(string $eventType, string $objectId, array $object = []): PaymentWebhookRequest
    {
        $payload = json_encode([
            'id' => 'evt_' . str_replace('.', '_', $eventType),
            'object' => 'event',
            'type' => $eventType,
            'data' => [
                'object' => array_merge([
                    'id' => $objectId,
                    'object' => str_starts_with($eventType, 'customer.') ? 'customer' : 'payment_method',
                ], $object),
            ],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_standard_test');

        return PaymentWebhookRequest::create(
            '/api/v1/payment_webhook/test/test',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        );
    }
}
