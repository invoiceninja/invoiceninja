<?php

namespace Tests\Feature;

use App\DataMapper\Billing\BillingContext;
use App\Http\Controllers\ClientPortal\NinjaPlanController;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Stripe\PaymentMethod;
use Tests\TestCase;

class NinjaPlanTrialCheckpointTest extends TestCase
{
    private NinjaPlanController $controller;

    private Account $account;

    private Client $client;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->createSchema();
        $this->controller = new TestableNinjaPlanController();
        $this->account = Account::withoutEvents(function (): Account {
            $account = (new Account())->forceFill([
                'key' => 'checkpoint-account',
                'plan' => null,
                'is_trial' => false,
                'billing_context' => null,
            ]);
            $account->save();

            return $account;
        });
        $this->client = Client::withoutEvents(function (): Client {
            $client = (new Client())->forceFill(['company_id' => 20]);
            $client->save();

            return $client;
        });
        $this->subscription = Subscription::withoutEvents(
            function (): Subscription {
                $subscription = (new Subscription())->forceFill([
                    'company_id' => 20,
                    'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
                ]);
                $subscription->save();

                return $subscription;
            }
        );
    }

    public function test_draft_checkpoint_auto_heals_without_creating_another_invoice(): void
    {
        $recurringInvoice = $this->createTrialRecurringInvoice(
            'ninja_plan_trial:pi_original',
            RecurringInvoice::STATUS_DRAFT
        );
        $this->persistCheckpoint($recurringInvoice);

        $resolved = $this->resolveCheckpoint();
        $period = $this->activate($resolved);
        $this->invoke(
            'finalizeTrialAccount',
            $this->account->fresh(),
            $period['started_at'],
            $period['expires_at']
        );

        $this->assertSame($recurringInvoice->id, $resolved->id);
        $this->assertSame(
            RecurringInvoice::STATUS_ACTIVE,
            RecurringInvoice::query()
                ->without('client')
                ->findOrFail($recurringInvoice->id)
                ->status_id
        );
        $this->assertSame(1, $this->trialInvoiceCount());

        $account = $this->account->fresh();
        $this->assertNotNull($account->trial_started);
        $this->assertSame(Account::PLAN_PRO, $account->plan);
        $this->assertSame(
            $recurringInvoice->id,
            $account->billing_context->recurring_invoice_id
        );
    }

    public function test_active_checkpoint_finalizes_without_restarting_or_recreating(): void
    {
        $nextSendDate = now()->addDays(9)->format('Y-m-d');
        $recurringInvoice = $this->createTrialRecurringInvoice(
            'ninja_plan_trial:pi_previous',
            RecurringInvoice::STATUS_ACTIVE,
            $nextSendDate
        );
        $this->persistCheckpoint($recurringInvoice);

        $resolved = $this->resolveCheckpoint();
        $period = $this->activate($resolved);
        $this->invoke(
            'finalizeTrialAccount',
            $this->account->fresh(),
            $period['started_at'],
            $period['expires_at']
        );

        $this->assertSame($recurringInvoice->id, $period['recurring_invoice']->id);
        $this->assertSame($nextSendDate, $period['expires_at']->format('Y-m-d'));
        $this->assertSame(1, $this->trialInvoiceCount());
        $this->assertSame(
            $nextSendDate,
            Carbon::parse($this->account->fresh()->plan_expires)->format('Y-m-d')
        );
    }

    public function test_checkpoint_from_previous_payment_intent_is_reused(): void
    {
        $recurringInvoice = $this->createTrialRecurringInvoice(
            'ninja_plan_trial:pi_previous_attempt',
            RecurringInvoice::STATUS_DRAFT
        );
        $this->persistCheckpoint($recurringInvoice);

        $resolved = $this->resolveCheckpoint();

        $this->assertSame($recurringInvoice->id, $resolved->id);
        $this->assertSame(
            'ninja_plan_trial:pi_previous_attempt',
            $resolved->private_notes
        );
        $this->assertSame(1, $this->trialInvoiceCount());
    }

    public function test_failure_before_checkpoint_recovers_the_exact_draft_marker(): void
    {
        $recurringInvoice = $this->createTrialRecurringInvoice(
            'ninja_plan_trial:pi_interrupted_before_checkpoint',
            RecurringInvoice::STATUS_DRAFT
        );

        $resolved = $this->invoke(
            'findTrialRecurringInvoiceByMarker',
            $this->client,
            $this->subscription,
            'ninja_plan_trial:pi_interrupted_before_checkpoint'
        );

        $this->assertSame($recurringInvoice->id, $resolved->id);
        $this->assertNull($this->account->fresh()->billing_context);
        $this->assertSame(1, $this->trialInvoiceCount());
    }

    public function test_failure_after_activation_is_recovered_and_finalized_once(): void
    {
        $recurringInvoice = $this->createTrialRecurringInvoice(
            'ninja_plan_trial:pi_interrupted_after_activation',
            RecurringInvoice::STATUS_ACTIVE,
            now()->addDays(14)->format('Y-m-d')
        );
        $this->persistCheckpoint($recurringInvoice);

        $resolved = $this->resolveCheckpoint();
        $period = $this->activate($resolved);
        $this->invoke(
            'finalizeTrialAccount',
            $this->account->fresh(),
            $period['started_at'],
            $period['expires_at']
        );

        $account = $this->account->fresh();
        $this->assertNotNull($account->trial_started);
        $this->assertSame($recurringInvoice->id, $account->billing_context->recurring_invoice_id);
        $this->assertSame(1, $this->trialInvoiceCount());

        $this->expectException(\LogicException::class);
        $this->invoke(
            'finalizeTrialAccount',
            $account,
            $period['started_at'],
            $period['expires_at']
        );
    }

    public function test_checkpoint_mismatch_fails_closed_without_creating_an_invoice(): void
    {
        $recurringInvoice = $this->createTrialRecurringInvoice(
            'ninja_plan_trial:pi_wrong_client',
            RecurringInvoice::STATUS_DRAFT,
            clientId: $this->client->id + 1
        );
        $this->persistCheckpoint($recurringInvoice);
        $invoiceCount = RecurringInvoice::query()->count();

        try {
            $this->resolveCheckpoint();
            $this->fail('An invalid checkpoint was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The trial checkpoint is invalid.', $exception->getMessage());
        }

        $this->assertSame($invoiceCount, RecurringInvoice::query()->count());
        $this->assertNull($this->account->fresh()->trial_started);
    }

    public function test_persisted_checkpoint_mismatch_matrix_fails_closed(): void
    {
        $cases = [
            ['company_id' => $this->subscription->company_id + 1],
            ['subscription_id' => $this->subscription->id + 1],
            ['private_notes' => 'not_a_trial_marker'],
            ['is_deleted' => true],
            ['status_id' => RecurringInvoice::STATUS_PAUSED],
            ['status_id' => RecurringInvoice::STATUS_COMPLETED],
            ['status_id' => RecurringInvoice::STATUS_PENDING],
        ];

        foreach ($cases as $index => $overrides) {
            $recurringInvoice = $this->createTrialRecurringInvoice(
                "ninja_plan_trial:pi_invalid_{$index}",
                RecurringInvoice::STATUS_DRAFT
            );
            RecurringInvoice::withoutEvents(function () use (
                $recurringInvoice,
                $overrides
            ): void {
                $recurringInvoice->forceFill($overrides)->save();
            });
            $this->persistCheckpoint($recurringInvoice);

            try {
                $this->resolveCheckpoint();
                $this->fail("Invalid checkpoint case {$index} was accepted.");
            } catch (RuntimeException) {
                $this->assertNull($this->account->fresh()->trial_started);
            }
        }

        $context = $this->account->fresh()->billing_context;
        $context->recurring_invoice_id = 999999;
        $this->account->billing_context = $context;
        Account::withoutEvents(fn() => $this->account->save());

        $this->expectException(RuntimeException::class);
        $this->resolveCheckpoint();
    }

    public function test_active_checkpoint_without_a_billing_date_fails_closed(): void
    {
        $recurringInvoice = $this->createTrialRecurringInvoice(
            'ninja_plan_trial:pi_missing_date',
            RecurringInvoice::STATUS_ACTIVE
        );
        $this->persistCheckpoint($recurringInvoice);

        $this->expectException(RuntimeException::class);
        $this->resolveCheckpoint();
    }

    public function test_checkpoint_persistence_preserves_existing_billing_context(): void
    {
        $this->account->billing_context = new BillingContext(
            client_id: $this->client->id,
            pricing: [
                'plan_price' => 14,
                'docuninja_price' => 8,
            ],
            docuninja_pending_prune: true
        );
        Account::withoutEvents(fn() => $this->account->save());
        $recurringInvoice = $this->createTrialRecurringInvoice(
            'ninja_plan_trial:pi_context',
            RecurringInvoice::STATUS_DRAFT
        );

        $this->persistCheckpoint($recurringInvoice);

        $context = $this->account->fresh()->billing_context;
        $this->assertSame($this->client->id, $context->client_id);
        $this->assertSame($recurringInvoice->id, $context->recurring_invoice_id);
        $this->assertSame(
            ['plan_price' => 14.0, 'docuninja_price' => 8.0],
            $context->pricing
        );
        $this->assertTrue($context->docuninja_pending_prune);
    }

    public function test_existing_gateway_token_is_reused_without_inserting_a_duplicate(): void
    {
        $gateway = (new CompanyGateway())->forceFill([
            'id' => 30,
            'company_id' => $this->client->company_id,
        ]);
        $token = $this->createGatewayToken($gateway, 'pm_existing');
        $driver = new FakeTrialGatewayDriver($this->client, $gateway);

        $stored = $this->invoke(
            'storeTrialGatewayToken',
            $this->client,
            $gateway,
            $driver,
            $this->paymentMethod('pm_existing'),
            ['customer_id' => 'cus_trial']
        );

        $this->assertSame($token->id, $stored->id);
        $this->assertSame(0, $driver->storeCalls);
        $this->assertSame(1, ClientGatewayToken::query()->count());
        $this->assertSame(1, (int) $stored->fresh()->is_default);
        $this->assertSame('cus_trial', $stored->fresh()->gateway_customer_reference);
    }

    public function test_soft_deleted_gateway_token_is_restored_and_reused(): void
    {
        $gateway = (new CompanyGateway())->forceFill([
            'id' => 31,
            'company_id' => $this->client->company_id,
        ]);
        $token = $this->createGatewayToken($gateway, 'pm_deleted');
        ClientGatewayToken::withoutEvents(fn() => $token->delete());
        $driver = new FakeTrialGatewayDriver($this->client, $gateway);

        $stored = $this->invoke(
            'storeTrialGatewayToken',
            $this->client,
            $gateway,
            $driver,
            $this->paymentMethod('pm_deleted'),
            ['customer_id' => 'cus_trial']
        );

        $this->assertSame($token->id, $stored->id);
        $this->assertSame(0, $driver->storeCalls);
        $this->assertNull($stored->fresh()->deleted_at);
        $this->assertSame(0, (int) $stored->fresh()->is_deleted);
        $this->assertSame(1, ClientGatewayToken::withTrashed()->count());
    }

    public function test_token_from_another_client_or_gateway_is_not_reused(): void
    {
        $otherGateway = (new CompanyGateway())->forceFill([
            'id' => 40,
            'company_id' => $this->client->company_id,
        ]);
        $currentGateway = (new CompanyGateway())->forceFill([
            'id' => 41,
            'company_id' => $this->client->company_id,
        ]);
        $this->createGatewayToken($otherGateway, 'pm_scoped');
        $driver = new FakeTrialGatewayDriver($this->client, $currentGateway);

        $stored = $this->invoke(
            'storeTrialGatewayToken',
            $this->client,
            $currentGateway,
            $driver,
            $this->paymentMethod('pm_scoped'),
            ['customer_id' => 'cus_trial']
        );

        $this->assertSame(1, $driver->storeCalls);
        $this->assertSame($currentGateway->id, $stored->company_gateway_id);
        $this->assertSame(2, ClientGatewayToken::query()->count());
    }

    public function test_retry_after_token_persistence_reuses_the_first_write(): void
    {
        $gateway = (new CompanyGateway())->forceFill([
            'id' => 50,
            'company_id' => $this->client->company_id,
        ]);
        $driver = new FakeTrialGatewayDriver($this->client, $gateway);
        $arguments = [
            $this->client,
            $gateway,
            $driver,
            $this->paymentMethod('pm_interrupted'),
            ['customer_id' => 'cus_trial'],
        ];

        $first = $this->invoke('storeTrialGatewayToken', ...$arguments);
        $second = $this->invoke('storeTrialGatewayToken', ...$arguments);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $driver->storeCalls);
        $this->assertSame(1, ClientGatewayToken::query()->count());
    }

    private function createSchema(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->nullable();
            $table->string('plan')->nullable();
            $table->string('plan_term')->nullable();
            $table->dateTime('plan_started')->nullable();
            $table->dateTime('plan_expires')->nullable();
            $table->dateTime('trial_started')->nullable();
            $table->string('trial_plan')->nullable();
            $table->boolean('is_trial')->default(false);
            $table->unsignedInteger('hosted_company_count')->default(0);
            $table->text('billing_context')->nullable();
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('frequency_id')->nullable();
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
        Schema::create('recurring_invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->integer('status_id');
            $table->string('private_notes')->nullable();
            $table->date('date')->nullable();
            $table->date('next_send_date')->nullable();
            $table->date('next_send_date_client')->nullable();
            $table->integer('remaining_cycles')->default(-1);
            $table->boolean('is_deleted')->default(false);
            $table->integer('deleted_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
        Schema::create('client_gateway_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('company_gateway_id');
            $table->unsignedInteger('gateway_type_id');
            $table->string('token');
            $table->string('gateway_customer_reference')->nullable();
            $table->text('meta')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->integer('deleted_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
    }

    private function createTrialRecurringInvoice(
        string $marker,
        int $status,
        ?string $nextSendDate = null,
        ?int $clientId = null
    ): RecurringInvoice {
        return RecurringInvoice::withoutEvents(
            function () use ($marker, $status, $nextSendDate, $clientId): RecurringInvoice {
                $recurringInvoice = (new RecurringInvoice())->forceFill([
                    'company_id' => $this->subscription->company_id,
                    'client_id' => $clientId ?? $this->client->id,
                    'subscription_id' => $this->subscription->id,
                    'private_notes' => $marker,
                    'status_id' => $status,
                    'next_send_date' => $nextSendDate,
                    'next_send_date_client' => $nextSendDate,
                    'remaining_cycles' => -1,
                    'is_deleted' => false,
                ]);
                $recurringInvoice->save();

                return $recurringInvoice;
            }
        );
    }

    private function persistCheckpoint(RecurringInvoice $recurringInvoice): void
    {
        $account = $this->account->fresh();
        $this->invoke(
            'setTrialBillingContext',
            $account,
            $this->client->id,
            $recurringInvoice->id
        );
        Account::withoutEvents(fn() => $account->save());
    }

    private function resolveCheckpoint(): RecurringInvoice
    {
        return $this->invoke(
            'resolveCheckpointedTrialRecurringInvoice',
            $this->account->fresh(),
            $this->client,
            $this->subscription
        );
    }

    /**
     * @return array{
     *     recurring_invoice: RecurringInvoice,
     *     started_at: Carbon,
     *     expires_at: Carbon
     * }
     */
    private function activate(RecurringInvoice $recurringInvoice): array
    {
        return RecurringInvoice::withoutEvents(
            fn(): array => $this->invoke(
                'activateTrialRecurringInvoice',
                $recurringInvoice,
                $this->client,
                $this->subscription
            )
        );
    }

    private function trialInvoiceCount(): int
    {
        return RecurringInvoice::query()
            ->where('client_id', $this->client->id)
            ->where('subscription_id', $this->subscription->id)
            ->count();
    }

    private function createGatewayToken(
        CompanyGateway $gateway,
        string $paymentMethodId
    ): ClientGatewayToken {
        return ClientGatewayToken::withoutEvents(function () use (
            $gateway,
            $paymentMethodId
        ): ClientGatewayToken {
            $token = (new ClientGatewayToken())->forceFill([
                'company_id' => $this->client->company_id,
                'client_id' => $this->client->id,
                'company_gateway_id' => $gateway->id,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'token' => $paymentMethodId,
                'gateway_customer_reference' => 'cus_old',
                'meta' => (object) [],
                'is_default' => false,
                'is_deleted' => false,
            ]);
            $token->save();

            return $token;
        });
    }

    private function paymentMethod(string $id): PaymentMethod
    {
        return PaymentMethod::constructFrom([
            'id' => $id,
            'type' => PaymentMethod::TYPE_CARD,
            'customer' => 'cus_trial',
            'card' => [
                'funding' => 'credit',
                'exp_month' => 12,
                'exp_year' => 2030,
                'brand' => 'visa',
                'last4' => '4242',
            ],
        ]);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod(NinjaPlanController::class, $method))
            ->invoke($this->controller, ...$arguments);
    }
}

class TestableNinjaPlanController extends NinjaPlanController
{
    protected function startTrialRecurringInvoice(
        RecurringInvoice $recurringInvoice
    ): RecurringInvoice {
        $recurringInvoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        RecurringInvoice::withoutEvents(fn() => $recurringInvoice->save());

        return RecurringInvoice::query()
            ->without('client')
            ->findOrFail($recurringInvoice->id);
    }
}

class FakeTrialGatewayDriver
{
    public int $storeCalls = 0;

    public function __construct(
        private readonly Client $client,
        private readonly CompanyGateway $gateway
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $additional
     */
    public function storeGatewayToken(
        array $data,
        array $additional = []
    ): ClientGatewayToken {
        $this->storeCalls++;
        $token = (new ClientGatewayToken())->forceFill([
            'company_id' => $this->client->company_id,
            'client_id' => $this->client->id,
            'company_gateway_id' => $this->gateway->id,
            'gateway_type_id' => $data['payment_method_id'],
            'token' => $data['token'],
            'gateway_customer_reference' => $additional['gateway_customer_reference'],
            'meta' => $data['payment_meta'],
            'is_default' => true,
            'is_deleted' => false,
        ]);
        ClientGatewayToken::withoutEvents(fn() => $token->save());

        return $token;
    }
}
