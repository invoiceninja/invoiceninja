<?php

namespace Tests\Unit\StripeConnect;

use App\Factory\ClientGatewayTokenFactory;
use App\Factory\CompanyGatewayFactory;
use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\PaymentDrivers\Stripe\PaymentMethodSyncService;
use App\Repositories\ClientGatewayTokenRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Modules\Admin\Jobs\Stripe\PaymentMethodWebhook;
use Tests\MockAccountData;
use Tests\TestCase;

class PaymentMethodWebhookTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private array $databases = [];

    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        $this->databases = MultiDB::$dbs;

        parent::setUp();

        $this->originalDatabase = config('database.default');

        if (!class_exists(PaymentMethodWebhook::class)) {
            $this->markTestSkipped('PaymentMethodWebhook job does not exist');
        }

        $this->makeTestData();
        MultiDB::$dbs = [$this->originalDatabase];
    }

    protected function tearDown(): void
    {
        if ($this->databases !== []) {
            MultiDB::$dbs = $this->databases;
        }

        if ($this->originalDatabase !== null) {
            MultiDB::setDb($this->originalDatabase);
        }

        parent::tearDown();
    }

    public function testDetachedPaymentMethodIsRemovedAndDefaultIsReassigned(): void
    {
        $gateway = $this->makeStripeGateway();
        $removed = $this->makeToken($gateway, 'pm_removed', true, 'Visa', '4242');
        $replacement = $this->makeToken($gateway, 'pm_replacement', false, 'Mastercard', '4444');

        $this->handle($this->event('payment_method.detached', 'pm_removed'));

        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $removed->id]);
        $this->assertTrue((bool) $replacement->fresh()->is_default);

        $activity = Activity::query()
            ->where('activity_type_id', Activity::PAYMENT_METHOD_REMOVED)
            ->where('client_id', $this->client->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Mastercard', $activity->notes);
        $this->assertStringContainsString('is now the default', $activity->notes);
    }

    public function testPaymentMethodServiceDeletesTokensThroughRepository(): void
    {
        $gateway = $this->makeStripeGateway();
        $removed = $this->makeToken($gateway, 'pm_repository');
        $repository = Mockery::mock(ClientGatewayTokenRepository::class)->makePartial();
        $repository->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(
                fn (ClientGatewayToken $token): bool => $token->is($removed)
            ))
            ->passthru();

        (new PaymentMethodSyncService($repository))->removePaymentMethod(
            $gateway->newCollection([$gateway]),
            'pm_repository'
        );

        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $removed->id]);
    }

    public function testDetachedPaymentMethodAcrossClonedGatewaysCreatesOneActivity(): void
    {
        $firstGateway = $this->makeStripeGateway();
        $secondGateway = $this->makeStripeGateway();
        $firstToken = $this->makeToken($firstGateway, 'pm_shared');
        $secondToken = $this->makeToken($secondGateway, 'pm_shared');

        $this->handle($this->event('payment_method.detached', 'pm_shared'));

        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $firstToken->id]);
        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $secondToken->id]);
        $this->assertSame(
            1,
            Activity::query()
                ->where('activity_type_id', Activity::PAYMENT_METHOD_REMOVED)
                ->where('client_id', $this->client->id)
                ->count()
        );
    }

    public function testCustomerDeletionRemovesCustomerTokensAndReassignsDefault(): void
    {
        $gateway = $this->makeStripeGateway();
        $first = $this->makeToken($gateway, 'pm_first', true, customerId: 'cus_deleted');
        $second = $this->makeToken($gateway, 'pm_second', false, customerId: 'cus_deleted');
        $remaining = $this->makeToken($gateway, 'pm_remaining', false, customerId: 'cus_active');

        $this->handle($this->event('customer.deleted', 'cus_deleted'));

        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $first->id]);
        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $second->id]);
        $this->assertTrue((bool) $remaining->fresh()->is_default);
        $this->assertDatabaseHas('clients', ['id' => $this->client->id, 'is_deleted' => false]);

        $activity = Activity::query()
            ->where('activity_type_id', Activity::PAYMENT_METHOD_REMOVED)
            ->where('client_id', $this->client->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('2 saved payment methods were removed', $activity->notes);
    }

    public function testPaymentMethodUpdateRefreshesExistingMetadata(): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeToken($gateway, 'pm_updated', false, 'Visa', '4242', 'cus_client', '8', '2026');

        $this->handle($this->event('payment_method.updated', 'pm_updated', [
            'type' => 'card',
            'card' => (object) [
                'brand' => 'mastercard',
                'last4' => '4444',
                'exp_month' => 10,
                'exp_year' => 2030,
            ],
        ]));

        $token = $token->fresh();

        $this->assertSame('mastercard', $token->meta->brand);
        $this->assertSame('4444', $token->meta->last4);
        $this->assertSame('10', $token->meta->exp_month);
        $this->assertSame('2030', $token->meta->exp_year);

        $activity = Activity::query()
            ->where('activity_type_id', Activity::PAYMENT_METHOD_UPDATED)
            ->where('client_id', $this->client->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Stripe updated', $activity->notes);
    }

    public function testAutomaticUpdateRecordsContextAndDuplicateIsANoOp(): void
    {
        $gateway = $this->makeStripeGateway();
        $this->makeToken($gateway, 'pm_automatic', false, 'Visa', '4242', 'cus_client', '8', '2026');
        $event = $this->event('payment_method.automatically_updated', 'pm_automatic', [
            'type' => 'card',
            'card' => (object) [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 8,
                'exp_year' => 2030,
            ],
        ]);

        $this->handle($event);
        $this->handle($event);

        $activities = Activity::query()
            ->where('activity_type_id', Activity::PAYMENT_METHOD_UPDATED)
            ->where('client_id', $this->client->id)
            ->get();

        $this->assertCount(1, $activities);
        $this->assertStringContainsString('Stripe automatically updated', $activities->first()->notes);
    }

    public function testAutomaticUpdateRefreshesExistingBacsMetadata(): void
    {
        $gateway = $this->makeStripeGateway();
        $token = $this->makeToken($gateway, 'pm_bacs', false, '108800', '0000');

        $this->handle($this->event('payment_method.automatically_updated', 'pm_bacs', [
            'type' => 'bacs_debit',
            'bacs_debit' => (object) [
                'sort_code' => '200000',
                'last4' => '6789',
            ],
        ]));

        $token = $token->fresh();

        $this->assertSame('200000', $token->meta->brand);
        $this->assertSame('6789', $token->meta->last4);
    }

    public function testUnknownPaymentMethodIsNotImported(): void
    {
        $this->makeStripeGateway();

        $this->handle($this->event('payment_method.updated', 'pm_unknown', [
            'type' => 'card',
            'card' => (object) ['brand' => 'visa', 'last4' => '4242'],
        ]));

        $this->assertFalse(ClientGatewayToken::query()->where('token', 'pm_unknown')->exists());
        $this->assertFalse(
            Activity::query()
                ->where('client_id', $this->client->id)
                ->whereIn('activity_type_id', [
                    Activity::PAYMENT_METHOD_UPDATED,
                    Activity::PAYMENT_METHOD_REMOVED,
                ])
                ->exists()
        );
    }

    public function testConfigFallbackBackfillsGatewayAccountId(): void
    {
        $gateway = $this->makeStripeGateway();
        $gateway->gateway_account_id = null;
        $gateway->save();
        $token = $this->makeToken($gateway, 'pm_fallback');

        $this->handle($this->event('payment_method.detached', 'pm_fallback'));

        $this->assertSoftDeleted('client_gateway_tokens', ['id' => $token->id]);
        $this->assertSame('acct_connected', $gateway->fresh()->gateway_account_id);
    }

    public function testOriginalDatabaseIsRestoredAfterGatewayMatch(): void
    {
        $gateway = $this->makeStripeGateway();
        $this->makeToken($gateway, 'pm_restore');
        $originalDatabase = config('database.default');
        config(['database.connections.stripe-origin' => config("database.connections.{$originalDatabase}")]);
        config(['database.default' => 'stripe-origin']);
        MultiDB::$dbs = [$originalDatabase];

        $this->handle($this->event('payment_method.detached', 'pm_restore'));

        $this->assertSame('stripe-origin', config('database.default'));
        config(['database.default' => $originalDatabase]);
    }

    public function testOriginalDatabaseIsRestoredWhenGatewayIsNotFound(): void
    {
        $originalDatabase = config('database.default');
        config(['database.connections.stripe-origin' => config("database.connections.{$originalDatabase}")]);
        config(['database.default' => 'stripe-origin']);
        MultiDB::$dbs = [$originalDatabase];

        $this->handle((object) [
            'account' => 'acct_missing',
            'type' => 'payment_method.detached',
            'data' => (object) ['object' => (object) ['id' => 'pm_missing']],
        ]);

        $this->assertSame('stripe-origin', config('database.default'));
        config(['database.default' => $originalDatabase]);
    }

    private function handle(\stdClass $event): void
    {
        (new PaymentMethodWebhook($event))->handle(
            app(PaymentMethodSyncService::class)
        );
    }

    private function makeStripeGateway(): CompanyGateway
    {
        $gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $gateway->accepted_credit_cards = 0;
        $gateway->fees_and_limits = new \stdClass();
        $gateway->gateway_account_id = 'acct_connected';
        $gateway->setConfig(['account_id' => 'acct_connected']);
        $gateway->save();

        return $gateway;
    }

    private function makeToken(
        CompanyGateway $gateway,
        string $tokenId,
        bool $isDefault = false,
        string $brand = 'Visa',
        string $last4 = '4242',
        string $customerId = 'cus_client',
        string $expMonth = '8',
        string $expYear = '2026'
    ): ClientGatewayToken {
        $token = ClientGatewayTokenFactory::create($this->company->id);
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $gateway->id;
        $token->gateway_type_id = 1;
        $token->gateway_customer_reference = $customerId;
        $token->token = $tokenId;
        $token->is_default = $isDefault;
        $token->meta = (object) [
            'brand' => $brand,
            'last4' => $last4,
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
            'type' => 1,
        ];
        $token->save();

        return $token;
    }

    private function event(string $type, string $objectId, array $object = []): \stdClass
    {
        return (object) [
            'id' => 'evt_'.str_replace('.', '_', $type),
            'account' => 'acct_connected',
            'type' => $type,
            'data' => (object) [
                'object' => (object) array_merge(['id' => $objectId], $object),
            ],
        ];
    }
}
