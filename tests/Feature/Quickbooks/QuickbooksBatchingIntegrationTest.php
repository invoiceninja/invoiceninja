<?php

namespace Tests\Feature\Quickbooks;

use Tests\TestCase;
use App\DataMapper\QuickbooksSettings;
use App\Jobs\Quickbooks\BatchPushToQuickbooks;
use App\Jobs\Quickbooks\PushToQuickbooks;
use App\Services\Quickbooks\QuickbooksBatchCollector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * Integration tests for complete QuickBooks batching flow
 *
 * Tests the full flow from batch collection → job dispatch
 * NOTE: Uses simple objects to avoid database dependencies
 */
class QuickbooksBatchingIntegrationTest extends TestCase
{
    private \stdClass $company1;
    private \stdClass $company2;
    private \stdClass $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();
        Queue::fake();

        // Create simple objects for tests (not database records)
        $this->user = new \stdClass();
        $this->user->id = 1;

        // Company 1
        $this->company1 = new \stdClass();
        $this->company1->id = 100;
        $this->company1->db = 'test-db-1';
        $this->company1->quickbooks = new QuickbooksSettings([
            'realmID' => 'realm-company-1',
            'accessTokenKey' => 'token1',
            'refresh_token' => 'refresh1',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'settings' => [
                'client' => ['direction' => 'push'],
            ],
        ]);

        // Company 2 (different realm)
        $this->company2 = new \stdClass();
        $this->company2->id = 200;
        $this->company2->db = 'test-db-2';
        $this->company2->quickbooks = new QuickbooksSettings([
            'realmID' => 'realm-company-2',
            'accessTokenKey' => 'token2',
            'refresh_token' => 'refresh2',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'settings' => [
                'client' => ['direction' => 'push'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        Cache::flush();
        parent::tearDown();
    }

    
    public function test_complete_flow_batches_entities_from_same_realm()
    {
        // Simulate 10 client updates from same company
        for ($i = 1; $i <= 10; $i++) {
            QuickbooksBatchCollector::collect(
                'client',
                $i,
                $this->company1->db,
                $this->company1->id,
                QuickbooksBatchCollector::PRIORITY_NORMAL
            );
        }

        // Verify batch is collecting
        $batchSize = QuickbooksBatchCollector::getBatchSize(
            'client',
            $this->company1->db,
            $this->company1->id
        );

        $this->assertEquals(10, $batchSize);

        // Flush batch should schedule a job
        Queue::assertPushed(\App\Jobs\Quickbooks\FlushQuickbooksBatch::class);
    }

    
    public function test_it_separates_batches_by_realm()
    {
        // Add clients from company 1
        for ($i = 1; $i <= 10; $i++) {
            QuickbooksBatchCollector::collect(
                'client',
                $i,
                $this->company1->db,
                $this->company1->id
            );
        }

        // Add clients from company 2 (different realm)
        for ($i = 11; $i <= 20; $i++) {
            QuickbooksBatchCollector::collect(
                'client',
                $i,
                $this->company2->db,
                $this->company2->id
            );
        }

        // Should have separate batches
        $batch1Size = QuickbooksBatchCollector::getBatchSize('client', $this->company1->db, $this->company1->id);
        $batch2Size = QuickbooksBatchCollector::getBatchSize('client', $this->company2->db, $this->company2->id);

        $this->assertEquals(10, $batch1Size);
        $this->assertEquals(10, $batch2Size);
    }

    
    public function test_immediate_priority_bypasses_batching()
    {
        QuickbooksBatchCollector::collect(
            'invoice',
            100,
            $this->company1->db,
            $this->company1->id,
            QuickbooksBatchCollector::PRIORITY_IMMEDIATE,
            forceImmediate: true
        );

        // Should dispatch single-entity job immediately
        Queue::assertPushed(PushToQuickbooks::class);

        // Should NOT batch
        Queue::assertNotPushed(BatchPushToQuickbooks::class);
    }

    
    public function test_max_batch_size_triggers_immediate_dispatch()
    {
        // Add 50 clients (MAX_BATCH_SIZE)
        for ($i = 1; $i <= 50; $i++) {
            QuickbooksBatchCollector::collect(
                'client',
                $i,
                $this->company1->db,
                $this->company1->id
            );
        }

        // Should dispatch batch job immediately
        Queue::assertPushed(BatchPushToQuickbooks::class, function ($job) {
            return count($job->entity_ids) === 50;
        });

        // Batch should be cleared
        $batchSize = QuickbooksBatchCollector::getBatchSize(
            'client',
            $this->company1->db,
            $this->company1->id
        );

        $this->assertEquals(0, $batchSize);
    }

    
    public function test_it_handles_mixed_entity_types_separately()
    {
        // Add clients
        for ($i = 1; $i <= 5; $i++) {
            QuickbooksBatchCollector::collect('client', $i, $this->company1->db, $this->company1->id);
        }

        // Add invoices (different entity type)
        for ($i = 100; $i <= 104; $i++) {
            QuickbooksBatchCollector::collect('invoice', $i, $this->company1->db, $this->company1->id);
        }

        // Should have separate batches
        $clientBatchSize = QuickbooksBatchCollector::getBatchSize('client', $this->company1->db, $this->company1->id);
        $invoiceBatchSize = QuickbooksBatchCollector::getBatchSize('invoice', $this->company1->db, $this->company1->id);

        $this->assertEquals(5, $clientBatchSize);
        $this->assertEquals(5, $invoiceBatchSize);
    }

    
    public function test_it_handles_mixed_priorities_separately()
    {
        // Add normal priority clients
        for ($i = 1; $i <= 5; $i++) {
            QuickbooksBatchCollector::collect(
                'client',
                $i,
                $this->company1->db,
                $this->company1->id,
                QuickbooksBatchCollector::PRIORITY_NORMAL
            );
        }

        // Add low priority clients
        for ($i = 10; $i <= 14; $i++) {
            QuickbooksBatchCollector::collect(
                'client',
                $i,
                $this->company1->db,
                $this->company1->id,
                QuickbooksBatchCollector::PRIORITY_LOW
            );
        }

        // Should have separate batches
        $normalBatchSize = QuickbooksBatchCollector::getBatchSize(
            'client',
            $this->company1->db,
            $this->company1->id,
            QuickbooksBatchCollector::PRIORITY_NORMAL
        );

        $lowBatchSize = QuickbooksBatchCollector::getBatchSize(
            'client',
            $this->company1->db,
            $this->company1->id,
            QuickbooksBatchCollector::PRIORITY_LOW
        );

        $this->assertEquals(5, $normalBatchSize);
        $this->assertEquals(5, $lowBatchSize);
    }

    
    public function test_multi_tenant_scenario_with_same_company_ids()
    {
        // Simulate multi-tenant: same company ID in different databases
        $db1 = 'tenant1';
        $db2 = 'tenant2';
        $companyId = 100; // Same ID in both databases

        // Add clients from tenant 1
        for ($i = 1; $i <= 10; $i++) {
            QuickbooksBatchCollector::collect('client', $i, $db1, $companyId);
        }

        // Add clients from tenant 2 (same company ID!)
        for ($i = 1; $i <= 10; $i++) {
            QuickbooksBatchCollector::collect('client', $i, $db2, $companyId);
        }

        // Should have completely separate batches
        $tenant1BatchSize = QuickbooksBatchCollector::getBatchSize('client', $db1, $companyId);
        $tenant2BatchSize = QuickbooksBatchCollector::getBatchSize('client', $db2, $companyId);

        $this->assertEquals(10, $tenant1BatchSize);
        $this->assertEquals(10, $tenant2BatchSize);

        // Trigger dispatch for tenant1
        QuickbooksBatchCollector::dispatchBatch('client', $db1, $companyId);

        // Only tenant1 should be cleared
        $this->assertEquals(0, QuickbooksBatchCollector::getBatchSize('client', $db1, $companyId));
        $this->assertEquals(10, QuickbooksBatchCollector::getBatchSize('client', $db2, $companyId));
    }

    
    public function test_deduplication_works_across_multiple_collects()
    {
        // Add same client multiple times
        QuickbooksBatchCollector::collect('client', 1, $this->company1->db, $this->company1->id);
        QuickbooksBatchCollector::collect('client', 1, $this->company1->db, $this->company1->id);
        QuickbooksBatchCollector::collect('client', 1, $this->company1->db, $this->company1->id);

        // Add another unique client
        QuickbooksBatchCollector::collect('client', 2, $this->company1->db, $this->company1->id);

        $batchSize = QuickbooksBatchCollector::getBatchSize('client', $this->company1->db, $this->company1->id);

        // Should only have 2 unique clients
        $this->assertEquals(2, $batchSize);
    }
}
