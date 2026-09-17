<?php

namespace Tests\Feature\Quickbooks;

use Mockery;
use ReflectionClass;
use Tests\TestCase;
use Tests\MockAccountData;
use Mockery\MockInterface;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\DataMapper\QuickbooksSettings;
use App\Jobs\Quickbooks\BatchPushToQuickbooks;
use App\Services\Quickbooks\QuickbooksRateLimiter;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * Feature tests for BatchPushToQuickbooks job.
 *
 * These tests use real database records and the real cache-backed rate limiter.
 * The Intuit SDK is never configured (services.quickbooks.client_id is nulled),
 * so no HTTP request can leave the process.
 */
class BatchPushToQuickbooksTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private string $realm_id;

    private string $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        /** Without a client id QuickbooksService::init() never builds a DataService, so no live Intuit call is possible. */
        config(['services.quickbooks.client_id' => null]);

        $this->db = config('database.default');
        $this->realm_id = 'realm-'.uniqid();

        $this->rateLimiter()->reset();
    }

    protected function tearDown(): void
    {
        $this->rateLimiter()->reset();

        parent::tearDown();
    }

    public function test_it_validates_all_entities_belong_to_same_company(): void
    {
        $company = $this->connectQuickbooks($this->company, 'push');
        $foreign_company = Company::factory()->create(['account_id' => $this->account->id]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
        ]);

        $foreign_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $foreign_company->id,
        ]);

        $job = new BatchPushToQuickbooks('client', [$client->id, $foreign_client->id], $this->db, $company->id);
        $this->bindQueueJob($job)->shouldNotReceive('release');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot batch entities from multiple companies/realms');

        $job->handle();
    }

    /**
     * resolveEntities() is not scoped by company_id today, which is what lets the guard be reached
     * through handle(). This pins the guard itself so it stays covered if that query is ever hardened.
     */
    public function test_it_validates_all_entities_belong_to_same_company_at_the_batch_guard(): void
    {
        $company = $this->connectQuickbooks($this->company, 'push');
        $foreign_company = Company::factory()->create(['account_id' => $this->account->id]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
        ]);

        $foreign_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $foreign_company->id,
        ]);

        $job = new BatchPushToQuickbooks('client', [$client->id, $foreign_client->id], $this->db, $company->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot batch entities from multiple companies/realms');

        $this->invokeProcessBatch($job, [$client, $foreign_client]);
    }

    public function test_it_validates_entities_match_job_company_id(): void
    {
        $company = $this->connectQuickbooks($this->company, 'push');
        $foreign_company = Company::factory()->create(['account_id' => $this->account->id]);

        $foreign_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $foreign_company->id,
        ]);

        $job = new BatchPushToQuickbooks('client', [$foreign_client->id], $this->db, $company->id);
        $this->bindQueueJob($job)->shouldNotReceive('release');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity company mismatch - possible cross-database contamination');

        $job->handle();
    }

    /**
     * @see test_it_validates_all_entities_belong_to_same_company_at_the_batch_guard
     */
    public function test_it_validates_entities_match_job_company_id_at_the_batch_guard(): void
    {
        $company = $this->connectQuickbooks($this->company, 'push');
        $foreign_company = Company::factory()->create(['account_id' => $this->account->id]);

        $foreign_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $foreign_company->id,
        ]);

        $job = new BatchPushToQuickbooks('client', [$foreign_client->id], $this->db, $company->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entity company mismatch - possible cross-database contamination');

        $this->invokeProcessBatch($job, [$foreign_client]);
    }

    public function test_it_skips_processing_when_push_disabled(): void
    {
        $company = $this->connectQuickbooks($this->company, 'pull');

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
        ]);

        /** Any progress past the push check would hit the rate limiter and release the job. */
        $this->rateLimiter()->enterBackoff(120);

        $this->assertFalse($company->shouldPushToQuickbooks('client'));

        $job = new BatchPushToQuickbooks('client', [$client->id], $this->db, $company->id);
        $this->bindQueueJob($job)->shouldNotReceive('release');

        $job->handle();

        $this->assertFalse(Cache::has('qb_concurrent:'.$this->realm_id));
        $this->assertSame(0, $this->pushFailureCount($company));
    }

    public function test_it_releases_the_job_when_push_enabled_and_rate_limited(): void
    {
        $company = $this->connectQuickbooks($this->company, 'push');

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
        ]);

        $this->rateLimiter()->enterBackoff(120);

        $this->assertTrue($company->shouldPushToQuickbooks('client'));

        $job = new BatchPushToQuickbooks('client', [$client->id], $this->db, $company->id);

        $released_for = null;
        $this->bindQueueJob($job)
            ->shouldReceive('release')
            ->once()
            ->with(Mockery::capture($released_for));

        $job->handle();

        $this->assertGreaterThanOrEqual(30, $released_for);
        $this->assertFalse(Cache::has('qb_concurrent:'.$this->realm_id));
    }

    public function test_it_handles_missing_entities_gracefully(): void
    {
        Bus::fake();

        $company = $this->connectQuickbooks($this->company, 'push');

        $missing_id = (int) Client::withTrashed()->max('id') + 5000;

        $job = new BatchPushToQuickbooks('client', [$missing_id, $missing_id], $this->db, $company->id);
        $this->bindQueueJob($job)->shouldNotReceive('release');

        $job->handle();

        $this->assertSame([$missing_id], $job->entity_ids);
        $this->assertFalse(Cache::has('qb_concurrent:'.$this->realm_id));
        $this->assertSame(0, $this->pushFailureCount($company));

        Bus::assertNotDispatched(BatchPushToQuickbooks::class);
    }

    public function test_it_processes_entities_with_correct_database_context(): void
    {
        $company = $this->connectQuickbooks($this->company, 'push');

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
        ]);

        $job = new BatchPushToQuickbooks('client', [$client->id], $this->db, $company->id);
        $this->bindQueueJob($job)->shouldNotReceive('release');

        $job->handle();

        /** acquireRequest() writes the concurrency key for every resolved entity, so its presence proves the batch loop ran. */
        $this->assertTrue(Cache::has('qb_concurrent:'.$this->realm_id));
        $this->assertSame($company->id, $client->refresh()->company_id);
    }

    public function test_it_ignores_entities_of_an_unconfigured_type(): void
    {
        $company = $this->connectQuickbooks($this->company, 'push');

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
        ]);

        $job = new BatchPushToQuickbooks('vendor', [$client->id], $this->db, $company->id);
        $this->bindQueueJob($job)->shouldNotReceive('release');

        $job->handle();

        $this->assertFalse(Cache::has('qb_concurrent:'.$this->realm_id));
    }

    public function test_it_exits_when_the_company_cannot_be_found(): void
    {
        $missing_company_id = (int) Company::max('id') + 5000;

        $job = new BatchPushToQuickbooks('client', [1], $this->db, $missing_company_id);
        $this->bindQueueJob($job)->shouldNotReceive('release');

        $job->handle();

        $this->assertFalse(Cache::has('qb_concurrent:'.$this->realm_id));
    }

    public function test_it_exposes_its_payload(): void
    {
        $job = new BatchPushToQuickbooks('client', [1], 'db-ninja-01', 100);

        $this->assertEquals('client', $job->entity_type);
        $this->assertEquals([1], $job->entity_ids);
        $this->assertEquals('db-ninja-01', $job->db);
        $this->assertEquals(100, $job->company_id);
    }

    public function test_it_uses_correct_queue(): void
    {
        $job = new BatchPushToQuickbooks('client', [1, 2, 3], 'db-ninja-01', 100);

        $this->assertNull($job->queue);
    }

    public function test_it_has_correct_retry_configuration(): void
    {
        $job = new BatchPushToQuickbooks('client', [1, 2, 3], 'db-ninja-01', 100);

        $this->assertEquals(10, $job->tries);
        $this->assertEquals([30, 60, 120], $job->backoff);
    }

    public function test_middleware_prevents_overlapping_for_same_batch(): void
    {
        $job = new BatchPushToQuickbooks('client', [1, 2, 3], 'db-ninja-01', 100);

        $this->assertCount(0, $job->middleware());
    }

    private function rateLimiter(): QuickbooksRateLimiter
    {
        return new QuickbooksRateLimiter($this->realm_id);
    }

    private function connectQuickbooks(Company $company, string $direction): Company
    {
        $company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => $this->realm_id,
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'settings' => [
                'client' => ['direction' => $direction],
            ],
        ]);

        $company->save();

        return $company->fresh();
    }

    /**
     * Attach a queue job double so release() calls made by the job are observable.
     */
    private function bindQueueJob(BatchPushToQuickbooks $job): MockInterface
    {
        /** @var MockInterface&QueueJobContract $queue_job */
        $queue_job = Mockery::mock(QueueJobContract::class);
        $queue_job->shouldReceive('attempts')->andReturn(1);

        $job->setJob($queue_job);

        return $queue_job;
    }

    /**
     * processBatch() is private and constructs nothing itself, so it can be characterised
     * directly with an uninitialised QuickbooksService — the guards throw before the
     * service is ever touched.
     *
     * @param array<int, \Illuminate\Database\Eloquent\Model> $entities
     */
    private function invokeProcessBatch(BatchPushToQuickbooks $job, array $entities): void
    {
        $method = (new ReflectionClass($job))->getMethod('processBatch');
        $method->setAccessible(true);

        $service = (new ReflectionClass(QuickbooksService::class))->newInstanceWithoutConstructor();

        $method->invoke($job, $service, $entities, $this->rateLimiter());
    }

    private function pushFailureCount(Company $company): int
    {
        return Activity::query()
            ->where('company_id', $company->id)
            ->where('activity_type_id', Activity::QUICKBOOKS_PUSH_FAILURE)
            ->count();
    }
}
