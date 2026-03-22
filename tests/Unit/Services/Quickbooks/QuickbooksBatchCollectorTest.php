<?php

namespace Tests\Unit\Services\Quickbooks;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Jobs\Quickbooks\PushToQuickbooks;
use App\Jobs\Quickbooks\BatchPushToQuickbooks;
use App\Jobs\Quickbooks\FlushQuickbooksBatch;
use App\Services\Quickbooks\QuickbooksBatchCollector;

/**
 * Tests for QuickBooks batch collector
 *
 */
class QuickbooksBatchCollectorTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        // Fake the queue
        Queue::fake();
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        Cache::flush();
        parent::tearDown();
    }

    
    public function test_it_dispatches_immediately_when_force_immediate_is_true()
    {
        QuickbooksBatchCollector::collect(
            'client',
            1,
            'db1',
            100,
            QuickbooksBatchCollector::PRIORITY_IMMEDIATE,
            forceImmediate: true
        );

        // Should dispatch PushToQuickbooks immediately
        Queue::assertPushed(PushToQuickbooks::class, 1);
        Queue::assertNotPushed(BatchPushToQuickbooks::class);
        Queue::assertNotPushed(FlushQuickbooksBatch::class);
    }

    
    public function test_it_dispatches_immediately_when_priority_is_immediate()
    {
        QuickbooksBatchCollector::collect(
            'client',
            1,
            'db1',
            100,
            QuickbooksBatchCollector::PRIORITY_IMMEDIATE
        );

        // Should dispatch PushToQuickbooks immediately
        Queue::assertPushed(PushToQuickbooks::class, 1);
    }

    public function test_it_collects_entities_in_batch()
    {
        QuickbooksBatchCollector::collect('client', 1, 'db1', 100);
        QuickbooksBatchCollector::collect('client', 2, 'db1', 100);
        QuickbooksBatchCollector::collect('client', 3, 'db1', 100);

        $batchSize = QuickbooksBatchCollector::getBatchSize('client', 'db1', 100);

        $this->assertEquals(3, $batchSize);

        // Should not dispatch yet (under MIN_BATCH_SIZE of 5)
        Queue::assertNotPushed(BatchPushToQuickbooks::class);
    }

    
    public function test_it_schedules_dispatch_when_min_batch_size_reached()
    {
        // Add 5 entities (MIN_BATCH_SIZE)
        for ($i = 1; $i <= 5; $i++) {
            QuickbooksBatchCollector::collect('client', $i, 'db1', 100);
        }

        // Should schedule a FlushQuickbooksBatch job
        Queue::assertPushed(FlushQuickbooksBatch::class, function ($job) {
            return true; // Just verify it was dispatched
        });
    }

    
    public function test_it_dispatches_immediately_when_max_batch_size_reached()
    {
        // Add 50 entities (MAX_BATCH_SIZE)
        for ($i = 1; $i <= 50; $i++) {
            QuickbooksBatchCollector::collect('client', $i, 'db1', 100);
        }

        // Should dispatch batch immediately
        Queue::assertPushed(BatchPushToQuickbooks::class, 1);

        // Batch should be cleared after dispatch
        $batchSize = QuickbooksBatchCollector::getBatchSize('client', 'db1', 100);
        $this->assertEquals(0, $batchSize);
    }

    
    public function test_it_deduplicates_entities_by_id()
    {
        QuickbooksBatchCollector::collect('client', 1, 'db1', 100);
        QuickbooksBatchCollector::collect('client', 1, 'db1', 100); // Duplicate
        QuickbooksBatchCollector::collect('client', 2, 'db1', 100);

        $batchSize = QuickbooksBatchCollector::getBatchSize('client', 'db1', 100);

        $this->assertEquals(2, $batchSize); // Only 2 unique entities
    }

    
    public function test_it_isolates_batches_by_database()
    {
        QuickbooksBatchCollector::collect('client', 1, 'db1', 100);
        QuickbooksBatchCollector::collect('client', 2, 'db2', 100); // Different DB

        $batchSize1 = QuickbooksBatchCollector::getBatchSize('client', 'db1', 100);
        $batchSize2 = QuickbooksBatchCollector::getBatchSize('client', 'db2', 100);

        $this->assertEquals(1, $batchSize1);
        $this->assertEquals(1, $batchSize2);
    }

    
    public function test_it_isolates_batches_by_company()
    {
        QuickbooksBatchCollector::collect('client', 1, 'db1', 100);
        QuickbooksBatchCollector::collect('client', 2, 'db1', 200); // Different company

        $batchSize1 = QuickbooksBatchCollector::getBatchSize('client', 'db1', 100);
        $batchSize2 = QuickbooksBatchCollector::getBatchSize('client', 'db1', 200);

        $this->assertEquals(1, $batchSize1);
        $this->assertEquals(1, $batchSize2);
    }

    
    public function test_it_isolates_batches_by_entity_type()
    {
        QuickbooksBatchCollector::collect('client', 1, 'db1', 100);
        QuickbooksBatchCollector::collect('invoice', 10, 'db1', 100); // Different entity

        $batchSizeClients = QuickbooksBatchCollector::getBatchSize('client', 'db1', 100);
        $batchSizeInvoices = QuickbooksBatchCollector::getBatchSize('invoice', 'db1', 100);

        $this->assertEquals(1, $batchSizeClients);
        $this->assertEquals(1, $batchSizeInvoices);
    }

    
    public function test_it_isolates_batches_by_priority()
    {
        QuickbooksBatchCollector::collect('client', 1, 'db1', 100, QuickbooksBatchCollector::PRIORITY_NORMAL);
        QuickbooksBatchCollector::collect('client', 2, 'db1', 100, QuickbooksBatchCollector::PRIORITY_LOW);

        $batchSizeNormal = QuickbooksBatchCollector::getBatchSize('client', 'db1', 100, QuickbooksBatchCollector::PRIORITY_NORMAL);
        $batchSizeLow = QuickbooksBatchCollector::getBatchSize('client', 'db1', 100, QuickbooksBatchCollector::PRIORITY_LOW);

        $this->assertEquals(1, $batchSizeNormal);
        $this->assertEquals(1, $batchSizeLow);
    }

    
    public function test_it_handles_multi_tenant_isolation_correctly()
    {
        // Same company ID but different databases (different tenants)
        QuickbooksBatchCollector::collect('client', 1, 'tenant1', 100);
        QuickbooksBatchCollector::collect('client', 2, 'tenant2', 100); // Same company ID, different DB

        $batchSizeTenant1 = QuickbooksBatchCollector::getBatchSize('client', 'tenant1', 100);
        $batchSizeTenant2 = QuickbooksBatchCollector::getBatchSize('client', 'tenant2', 100);

        $this->assertEquals(1, $batchSizeTenant1);
        $this->assertEquals(1, $batchSizeTenant2);

        // Add enough entities to tenant1 to trigger batch dispatch (need 50 total)
        for ($i = 2; $i <= 50; $i++) {
            QuickbooksBatchCollector::collect('client', $i, 'tenant1', 100);
        }

        // Should dispatch batch for tenant1
        Queue::assertPushed(BatchPushToQuickbooks::class, function ($job) {
            return $job->db === 'tenant1' && $job->company_id === 100;
        });

        // tenant2 batch should not be affected (still has only 1 entity)
        $this->assertEquals(1, QuickbooksBatchCollector::getBatchSize('client', 'tenant2', 100));
    }

    
    public function test_dispatch_batch_clears_cache()
    {
        // Collect some entities
        for ($i = 1; $i <= 10; $i++) {
            QuickbooksBatchCollector::collect('client', $i, 'db1', 100);
        }

        $this->assertEquals(10, QuickbooksBatchCollector::getBatchSize('client', 'db1', 100));

        // Manually dispatch batch
        QuickbooksBatchCollector::dispatchBatch('client', 'db1', 100, QuickbooksBatchCollector::PRIORITY_NORMAL);

        // Batch should be cleared
        $this->assertEquals(0, QuickbooksBatchCollector::getBatchSize('client', 'db1', 100));

        // Job should be dispatched
        Queue::assertPushed(BatchPushToQuickbooks::class);
    }

    
    public function test_it_does_not_schedule_duplicate_flushes()
    {
        // Add 5 entities to trigger scheduling
        for ($i = 1; $i <= 5; $i++) {
            QuickbooksBatchCollector::collect('client', $i, 'db1', 100);
        }

        // Add more entities (should not schedule again)
        for ($i = 6; $i <= 10; $i++) {
            QuickbooksBatchCollector::collect('client', $i, 'db1', 100);
        }

        // Should only schedule once
        Queue::assertPushed(FlushQuickbooksBatch::class, 1);
    }

    
    public function test_it_dispatches_with_correct_parameters()
    {
        // Collect 50 entities to trigger immediate dispatch
        for ($i = 1; $i <= 50; $i++) {
            QuickbooksBatchCollector::collect('invoice', $i, 'mydb', 999);
        }

        Queue::assertPushed(BatchPushToQuickbooks::class, function ($job) {
            return $job->entity_type === 'invoice'
                && count($job->entity_ids) === 50
                && $job->db === 'mydb'
                && $job->company_id === 999;
        });
    }
}
