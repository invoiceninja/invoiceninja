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

namespace Tests\Unit\Services\Quickbooks;

use Mockery;
use Tests\TestCase;
use ReflectionClass;
use RuntimeException;
use Tests\MockAccountData;
use App\DataMapper\ClientSync;
use App\Factory\ClientFactory;
use App\DataMapper\QuickbooksSettings;
use Illuminate\Support\Facades\Cache;
use App\Services\Quickbooks\SdkWrapper;
use App\Services\Quickbooks\Models\QbClient;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Jobs\QuickbooksImport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class QuickbooksImportResumeTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();

        $this->makeTestData();
        $this->configureQuickbooks();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_initial_sync_cursor_key_uses_db_company_realm_and_entity(): void
    {
        $job = $this->makeJob();

        $key = $this->invokePrivate($job, 'initialSyncCursorCacheKey', ['Customer']);

        $this->assertSame(
            "quickbooks:initial-sync:v1:{$this->company->db}:{$this->company->id}:test-realm:Customer:cursor",
            $key
        );

        $this->invokePrivate($job, 'storeInitialSyncCursor', ['Customer', 1001, 500]);

        $cursor = Cache::get($key);

        $this->assertSame(1001, $cursor['start_position']);
        $this->assertSame(500, $cursor['page_size']);
        $this->assertSame('running', $cursor['status']);
    }

    public function test_overlap_lock_outlives_the_job_timeout(): void
    {
        $job = $this->makeJob();
        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame("qbs-{$this->company->id}-{$this->company->db}", $middleware[0]->key);
        $this->assertSame($job->timeout + 300, $middleware[0]->expiresAfter);
    }

    public function test_filter_already_imported_skips_existing_soft_deleted_qb_ids(): void
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $sync = new ClientSync();
        $sync->qb_id = '10';
        $client->sync = $sync;
        $client->saveQuietly();
        $client->delete();

        $job = $this->makeJob();

        $filtered = $this->invokePrivate($job, 'filterAlreadyImported', [
            'Customer',
            [(object) ['Id' => '10'], (object) ['Id' => '11']],
        ]);

        $this->assertCount(1, $filtered);
        $this->assertSame('11', $filtered[0]->Id);
    }

    public function test_process_entity_sync_routes_sales_receipts_to_invoice_sync(): void
    {
        $records = [(object) ['Id' => '20']];
        $job = $this->makeJob();

        $service = Mockery::mock(QuickbooksService::class);
        $invoice = Mockery::mock(QbInvoice::class);
        $invoice->shouldReceive('syncToNinja')
            ->once()
            ->with($records);

        $service->invoice = $invoice;
        $this->setPrivate($job, 'qbs', $service);

        $this->invokePrivate($job, 'processEntitySync', ['SalesReceipt', $records]);

        $this->addToAssertionCount(1);
    }

    public function test_initial_sync_page_filters_existing_records_and_marks_entity_completed(): void
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $sync = new ClientSync();
        $sync->qb_id = '1';
        $client->sync = $sync;
        $client->saveQuietly();

        $job = $this->makeJob();
        $service = Mockery::mock(QuickbooksService::class);
        $sdk = Mockery::mock(SdkWrapper::class);
        $qb_client = Mockery::mock(QbClient::class);

        $records = [(object) ['Id' => '1'], (object) ['Id' => '2']];

        $service->shouldReceive('sdk')
            ->once()
            ->andReturn($sdk);
        $sdk->shouldReceive('fetchRecordsPage')
            ->once()
            ->with('Customer', 1, 500)
            ->andReturn($records);
        $qb_client->shouldReceive('syncToNinja')
            ->once()
            ->with(Mockery::on(fn (array $records): bool => count($records) === 1 && $records[0]->Id === '2'));

        $service->client = $qb_client;
        $this->setPrivate($job, 'qbs', $service);

        $this->invokePrivate($job, 'performInitialSyncForEntity', ['Customer']);

        $cursor = Cache::get($this->invokePrivate($job, 'initialSyncCursorCacheKey', ['Customer']));

        $this->assertSame('completed', $cursor['status']);
        $this->assertNull($cursor['start_position']);
    }

    public function test_failed_page_keeps_cursor_on_same_start_position(): void
    {
        $job = $this->makeJob();
        $service = Mockery::mock(QuickbooksService::class);
        $sdk = Mockery::mock(SdkWrapper::class);
        $qb_client = Mockery::mock(QbClient::class);

        $service->shouldReceive('sdk')
            ->once()
            ->andReturn($sdk);
        $sdk->shouldReceive('fetchRecordsPage')
            ->once()
            ->with('Customer', 1, 500)
            ->andReturn([(object) ['Id' => '50']]);
        $qb_client->shouldReceive('syncToNinja')
            ->once()
            ->andThrow(new RuntimeException('page failed'));

        $service->client = $qb_client;
        $this->setPrivate($job, 'qbs', $service);

        try {
            $this->invokePrivate($job, 'performInitialSyncForEntity', ['Customer']);
            $this->fail('Expected page failure was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('page failed', $e->getMessage());
        }

        $cursor = Cache::get($this->invokePrivate($job, 'initialSyncCursorCacheKey', ['Customer']));

        $this->assertSame('running', $cursor['status']);
        $this->assertSame(1, $cursor['start_position']);
    }

    private function configureQuickbooks(): void
    {
        $this->company->db = $this->company->db ?: 'db-ninja-01';
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => [],
        ]);
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function makeJob(array $syncable = ['Customer']): QuickbooksImport
    {
        $job = new QuickbooksImport($this->company->id, $this->company->db, $syncable);
        $this->setPrivate($job, 'company', $this->company);

        return $job;
    }

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $reflection->getProperty($property)->setValue($object, $value);
    }

    private function invokePrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);

        return $reflection->getMethod($method)->invokeArgs($object, $args);
    }
}
