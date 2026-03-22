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

namespace App\Services\Quickbooks;

use Illuminate\Support\Facades\Cache;
use App\Jobs\Quickbooks\BatchPushToQuickbooks;

/**
 * Collects entities for batching before pushing to QuickBooks.
 *
 * Instead of immediately dispatching a job for each entity change,
 * this collector groups entities together and dispatches batch jobs periodically.
 *
 * Benefits:
 * - Reduces queue overhead (fewer jobs)
 * - Better rate limit handling
 * - More efficient API usage
 */
class QuickbooksBatchCollector
{
    /**
     * Dispatch immediately if batch reaches this size (don't wait for timer)
     */
    private const MAX_BATCH_SIZE = 50;

    /**
     * Time windows for collecting entities (seconds) by priority.
     * After this window a FlushQuickbooksBatch job fires and pushes
     * whatever has accumulated — even a single entity.
     */
    private const COLLECTION_WINDOW_NORMAL = 10;
    private const COLLECTION_WINDOW_LOW = 30;

    /**
     * Priority levels
     */
    public const PRIORITY_IMMEDIATE = 'immediate';  // Creates, status changes
    public const PRIORITY_NORMAL = 'normal';        // Regular updates
    public const PRIORITY_LOW = 'low';              // Bulk/background updates

    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'qb_batch_collector';

    /**
     * Add an entity to the batch collector
     *
     * IMPORTANT: All entities in a batch MUST belong to the same QuickBooks realm.
     * Since each Invoice Ninja company has exactly one QB connection (realm),
     * batching by companyId ensures realm isolation.
     *
     * @param string $entityType 'client', 'invoice', etc.
     * @param int $entityId Entity ID
     * @param string $db Database name
     * @param int $companyId Company ID (implicitly defines the QB realm)
     * @param string $priority Priority level (immediate, normal, low)
     * @param bool $forceImmediate Force immediate dispatch (bypass batching)
     * @return void
     */
    public static function collect(
        string $entityType,
        int $entityId,
        string $db,
        int $companyId,
        string $priority = self::PRIORITY_NORMAL,
        bool $forceImmediate = false
    ): void {
        // Skip collection if we're currently importing from QB (prevent circular sync)
        if (!empty(QuickbooksService::$importing[$companyId])) {
            nlog("QB Batch: Skipping {$entityType} {$entityId} — currently importing from QB for company {$companyId}");
            return;
        }

        nlog("QB Batch: Collecting {$entityType} {$entityId} for company {$companyId} with priority {$priority}");

        // Force immediate dispatch for high-priority operations
        if ($forceImmediate || $priority === self::PRIORITY_IMMEDIATE) {
            nlog("QB Batch: Immediate dispatch for {$entityType} {$entityId}");
            \App\Jobs\Quickbooks\PushToQuickbooks::dispatch($entityType, $entityId, $db);
            return;
        }

        $key = self::getCacheKey($entityType, $db, $companyId, $priority);

        // Get current batch
        $batch = Cache::get($key, []);

        // Add entity ID (using ID as key to avoid duplicates)
        $batch[$entityId] = [
            'id' => $entityId,
            'added_at' => time(),
            'priority' => $priority,
        ];

        $collectionWindow = self::getCollectionWindow($priority);

        // Store batch — TTL must outlive the flush job delay so data is
        // still in cache when FlushQuickbooksBatch runs.
        Cache::put($key, $batch, now()->addSeconds($collectionWindow + 30));

        if (count($batch) >= self::MAX_BATCH_SIZE) {
            // Batch is full, dispatch right now
            self::dispatchBatch($entityType, $db, $companyId, $priority);
        } else {
            // Schedule a delayed flush (idempotent — won't double-schedule)
            self::scheduleDispatch($entityType, $db, $companyId, $priority, $collectionWindow);
        }
    }

    /**
     * Dispatch a batch job immediately
     *
     * @param string $entityType
     * @param string $db
     * @param int $companyId
     * @param string $priority Priority level
     * @return void
     */
    public static function dispatchBatch(string $entityType, string $db, int $companyId, string $priority = self::PRIORITY_NORMAL): void
    {
        $key = self::getCacheKey($entityType, $db, $companyId, $priority);

        // Get and clear batch
        $batch = Cache::pull($key, []);

        if (empty($batch)) {
            return;
        }

        $entityIds = array_column($batch, 'id');

        nlog("QB Batch Collector: Dispatching {$priority} batch of " . count($entityIds) . " {$entityType}(s)");

        // Dispatch batch job
        BatchPushToQuickbooks::dispatch($entityType, $entityIds, $db, $companyId);

        // Cancel any scheduled dispatch
        $scheduleKey = self::getScheduleKey($entityType, $db, $companyId, $priority);
        Cache::forget($scheduleKey);
    }

    /**
     * Schedule a batch dispatch after collection window expires
     *
     * @param string $entityType
     * @param string $db
     * @param int $companyId
     * @param string $priority Priority level
     * @param int $collectionWindow Window in seconds
     * @return void
     */
    private static function scheduleDispatch(
        string $entityType,
        string $db,
        int $companyId,
        string $priority,
        int $collectionWindow
    ): void {
        $scheduleKey = self::getScheduleKey($entityType, $db, $companyId, $priority);

        // Only schedule if not already scheduled
        if (Cache::has($scheduleKey)) {
            return;
        }

        // Mark as scheduled
        Cache::put($scheduleKey, true, now()->addSeconds($collectionWindow + 5));

        // Dispatch delayed job to flush the batch
        \App\Jobs\Quickbooks\FlushQuickbooksBatch::dispatch($entityType, $db, $companyId, $priority)
            ->delay(now()->addSeconds($collectionWindow));
    }

    /**
     * Get collection window based on priority
     *
     * @param string $priority
     * @return int Seconds
     */
    private static function getCollectionWindow(string $priority): int
    {
        return match ($priority) {
            self::PRIORITY_LOW => self::COLLECTION_WINDOW_LOW,
            default => self::COLLECTION_WINDOW_NORMAL,
        };
    }

    /**
     * Force dispatch all pending batches (for shutdown/testing)
     *
     * @return void
     */
    public static function flushAll(): void
    {
        // This would require tracking all active batches
        // For now, individual batches are flushed by scheduled jobs
        nlog("QB Batch Collector: Flush all requested");
    }

    /**
     * Get cache key for batch
     *
     * CRITICAL: Key uses COMPOSITE of db + companyId for multi-tenant isolation:
     * - Multiple databases can have companies with same IDs
     * - db "tenant1" company 100 ≠ db "tenant2" company 100
     * - Each Invoice Ninja company = one QB connection = one realm
     * - Entities from different databases/companies/realms NEVER batch together
     *
     * Example keys:
     * - qb_batch_collector:client:tenant1:100:normal
     * - qb_batch_collector:client:tenant2:100:normal (different batch!)
     *
     * @param string $entityType
     * @param string $db Database name (for multi-tenant isolation)
     * @param int $companyId Company ID within that database
     * @param string $priority Priority level
     * @return string
     */
    private static function getCacheKey(string $entityType, string $db, int $companyId, string $priority = self::PRIORITY_NORMAL): string
    {
        // Composite key: db + companyId ensures multi-tenant isolation
        return self::CACHE_PREFIX . ":{$entityType}:{$db}:{$companyId}:{$priority}";
    }

    /**
     * Get cache key for schedule tracking
     *
     * Uses same composite db + companyId structure for multi-tenant isolation
     *
     * @param string $entityType
     * @param string $db Database name (for multi-tenant isolation)
     * @param int $companyId Company ID within that database
     * @param string $priority Priority level
     * @return string
     */
    private static function getScheduleKey(string $entityType, string $db, int $companyId, string $priority = self::PRIORITY_NORMAL): string
    {
        // Composite key: db + companyId ensures multi-tenant isolation
        return self::CACHE_PREFIX . ":schedule:{$entityType}:{$db}:{$companyId}:{$priority}";
    }

    /**
     * Get current batch size
     *
     * @param string $entityType
     * @param string $db
     * @param int $companyId
     * @param string $priority Priority level
     * @return int
     */
    public static function getBatchSize(string $entityType, string $db, int $companyId, string $priority = self::PRIORITY_NORMAL): int
    {
        $key = self::getCacheKey($entityType, $db, $companyId, $priority);
        $batch = Cache::get($key, []);

        return count($batch);
    }
}
