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

namespace App\Jobs\Quickbooks;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\Company;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\QuickbooksRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use QuickBooksOnline\API\Exception\ServiceException;

/**
 * Batch job to push multiple entities to QuickBooks.
 *
 * This job handles pushing batches of entities (clients, invoices, etc.) to QuickBooks
 * with intelligent rate limiting and error handling.
 *
 * Benefits over single-entity jobs:
 * - Reduces queue overhead
 * - Enables intelligent batching and throttling
 * - Centralized rate limit handling
 * - Better error recovery
 */
class BatchPushToQuickbooks implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Allow many release() cycles without burning attempts. */
    public $tries = 10;

    /** Only 3 actual exceptions cause permanent failure. */
    public $maxExceptions = 3;

    public $deleteWhenMissingModels = true;

    /**
     * Backoff strategy in seconds (exponential)
     */
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     *
     * @param string $entity_type Entity type: 'client', 'invoice', etc.
     * @param array<int> $entity_ids Array of entity IDs to push
     * @param string $db The database name
     * @param int $company_id The company ID
     */
    public function __construct(
        public string $entity_type,
        public array $entity_ids,
        public string $db,
        public int $company_id
    ) {
        // Set queue to dedicated QB queue
        // $this->onQueue('quickbooks');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        nlog("QB Batch: Attempt {$this->attempts()}/{$this->tries} for {$this->entity_type} [" . implode(',', $this->entity_ids) . "] company {$this->company_id}");

        MultiDB::setDb($this->db);

        $company = Company::find($this->company_id);

        if (!$company || !$company->quickbooks) {
            nlog("QB Batch: Company {$this->company_id} not found or QB not configured");
            return;
        }

        // Check if push is still enabled
        if (!$company->shouldPushToQuickbooks($this->entity_type)) {
            nlog("QB Batch: Push disabled for {$this->entity_type} in company {$company->company_key}");
            return;
        }

        // Initialize rate limiter
        $rateLimiter = new QuickbooksRateLimiter($company->quickbooks->realmID);

        // Log rate limit status
        $status = $rateLimiter->getStatus();
        nlog("QB Batch: Rate limit status before processing", $status);

        // Wait for capacity if needed (max 60 seconds)
        if (!$rateLimiter->waitForCapacity(60)) {
            nlog("QB Batch: Rate limit capacity not available, releasing job for 60 seconds");
            $this->release(60);
            return;
        }

        // Resolve entities
        $entities = $this->resolveEntities();

        if (empty($entities)) {
            nlog("QB Batch: No entities found for IDs: " . implode(', ', $this->entity_ids));
            return;
        }

        nlog("QB Batch: Processing {$this->entity_type} batch of " . count($entities) . " entities");

        // Initialize QB service
        $qbService = new QuickbooksService($company);

        // Process batch with rate limiting
        $this->processBatch($qbService, $entities, $rateLimiter);
    }

    /**
     * Process the batch of entities with rate limiting
     *
     * CRITICAL: All entities MUST belong to the same database + company + realm.
     * This is validated at the start of processing.
     *
     * @param QuickbooksService $qbService
     * @param array $entities
     * @param QuickbooksRateLimiter $rateLimiter
     * @return void
     */
    private function processBatch(QuickbooksService $qbService, array $entities, QuickbooksRateLimiter $rateLimiter): void
    {
        // SAFETY CHECK: Verify all entities belong to the same company
        $companyIds = array_unique(array_map(fn($e) => $e->company_id, $entities));
        if (count($companyIds) > 1) {
            nlog("QB Batch ERROR: Entities from multiple companies detected!", [
                'expected_company_id' => $this->company_id,
                'found_company_ids' => $companyIds,
                'expected_db' => $this->db,
            ]);
            throw new \RuntimeException("Cannot batch entities from multiple companies/realms");
        }

        // Additional sanity check: all entities should match the job's company_id
        if (!in_array($this->company_id, $companyIds)) {
            nlog("QB Batch ERROR: Entities don't match job company ID!", [
                'job_company_id' => $this->company_id,
                'entity_company_ids' => $companyIds,
            ]);
            throw new \RuntimeException("Entity company mismatch - possible cross-database contamination");
        }

        $successCount = 0;
        $failureCount = 0;
        $rateLimitedIds = [];

        foreach ($entities as $entity) {
            // Check rate limit before each entity
            if (!$rateLimiter->canMakeRequest()) {
                $delay = $rateLimiter->getRecommendedDelay();
                nlog("QB Batch: Rate limit reached, pausing for {$delay} seconds");

                // Wait for recommended delay
                if ($delay > 0 && $delay <= 10) {
                    sleep($delay);
                } else {
                    // Delay too long, re-queue remaining entities
                    $rateLimitedIds[] = $entity->id;
                    continue;
                }
            }

            // Track request
            $requestToken = $rateLimiter->acquireRequest();

            try {
                // Process single entity
                $this->processEntity($qbService, $entity);

                $entity->refresh();
                $this->logActivitySuccess($entity);

                $rateLimiter->trackRequest();
                $successCount++;

            } catch (ServiceException $e) {
                $this->handleServiceException($e, $entity, $rateLimiter);
                $failureCount++;

                // If rate limited, stop processing and re-queue
                if ($this->isRateLimitException($e)) {
                    $rateLimitedIds[] = $entity->id;

                    // Collect remaining unprocessed IDs
                    $remainingEntities = array_slice($entities, array_search($entity, $entities) + 1);
                    foreach ($remainingEntities as $remaining) {
                        $rateLimitedIds[] = $remaining->id;
                    }
                    break;
                }

            } catch (\Throwable $e) {
                nlog("QB Batch: Failed to push {$this->entity_type} {$entity->id}: " . $e->getMessage());
                $this->logActivityFailure($entity, $this->extractReadableError($e->getMessage()));
                $failureCount++;

            } finally {
                $rateLimiter->releaseRequest($requestToken);
            }
        }

        // Re-queue rate-limited entities
        if (!empty($rateLimitedIds)) {
            $delay = $rateLimiter->getRecommendedDelay();
            nlog("QB Batch: Re-queuing " . count($rateLimitedIds) . " entities due to rate limiting (delay: {$delay}s)");

            self::dispatch($this->entity_type, $rateLimitedIds, $this->db, $this->company_id)
                ->delay(now()->addSeconds($delay));
        }

        nlog("QB Batch: Completed - Success: {$successCount}, Failed: {$failureCount}, Rate Limited: " . count($rateLimitedIds));
    }

    /**
     * Process a single entity
     *
     * @param QuickbooksService $qbService
     * @param mixed $entity
     * @return void
     */
    private function processEntity(QuickbooksService $qbService, $entity): void
    {
        match ($this->entity_type) {
            'client' => $qbService->client->syncToForeign([$entity]),
            'invoice' => $qbService->invoice->syncToForeign([$entity]),
            'product' => $qbService->product->syncToForeign([$entity]),
            'payment' => $qbService->payment->syncToForeign([$entity]),
            default => throw new \InvalidArgumentException("Unsupported entity type: {$this->entity_type}"),
        };
    }

    /**
     * Handle QuickBooks service exceptions
     *
     * @param ServiceException $e
     * @param mixed $entity
     * @param QuickbooksRateLimiter $rateLimiter
     * @return void
     */
    private function handleServiceException(ServiceException $e, $entity, QuickbooksRateLimiter $rateLimiter): void
    {
        $statusCode = $e->getCode();
        $errorMessage = $e->getMessage();

        nlog("QB Batch: ServiceException for {$this->entity_type} {$entity->id}", [
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
        ]);

        // Handle rate limiting (429 or throttle messages)
        if ($this->isRateLimitException($e)) {
            $backoffSeconds = $this->calculateBackoff();
            $rateLimiter->enterBackoff($backoffSeconds);

            nlog("QB Batch: Rate limit exceeded, entering backoff for {$backoffSeconds} seconds");
            return;
        }

        // Handle authentication errors
        if ($statusCode === 401) {
            nlog("QB Batch: Authentication failed, may need token refresh");
        }

        $this->logActivityFailure($entity, $this->extractReadableError($errorMessage));
    }

    /**
     * Check if exception is a rate limit error
     *
     * @param ServiceException $e
     * @return bool
     */
    private function isRateLimitException(ServiceException $e): bool
    {
        $statusCode = $e->getCode();
        $errorMessage = $e->getMessage();

        return $statusCode === 429
            || str_contains(strtolower($errorMessage), 'throttle')
            || str_contains(strtolower($errorMessage), 'rate limit');
    }

    /**
     * Calculate exponential backoff based on attempt number
     *
     * @return int Seconds to backoff
     */
    private function calculateBackoff(): int
    {
        $attempt = $this->attempts();

        return match (true) {
            $attempt <= 1 => 60,    // First retry: 1 minute
            $attempt == 2 => 120,   // Second retry: 2 minutes
            default => 300,         // Third+ retry: 5 minutes
        };
    }

    /**
     * Extract a human-readable error from the SDK's raw exception message.
     *
     * The SDK formats messages as:
     * "Request is not made successful. Response Code:[400] with body: [<XML>]."
     *
     * The XML body contains <Message> and <Detail> elements we can extract.
     */
    private function extractReadableError(string $rawMessage): string
    {
        // Try to extract the XML body from the SDK message
        if (preg_match('/with body:\s*\[(.+)\]/s', $rawMessage, $matches)) {
            $body = trim($matches[1]);

            try {
                $xml = @simplexml_load_string($body);
                if ($xml !== false && isset($xml->Fault->Error)) {
                    $error = $xml->Fault->Error;
                    $message = (string) ($error->Message ?? '');
                    $detail = (string) ($error->Detail ?? '');

                    if ($message && $detail) {
                        return "{$message} - {$detail}";
                    }

                    return $message ?: $detail;
                }
            } catch (\Throwable $e) {
                // XML parsing failed, fall through to truncation
            }
        }

        // Fallback: return a cleaned/truncated version of the raw message
        $cleaned = str_replace('Request is not made successful. ', '', $rawMessage);

        return mb_substr($cleaned, 0, 500);
    }

    /**
     * Log a QuickBooks push success as an Activity record visible to the user.
     */
    private function logActivitySuccess($entity): void
    {
        try {
            $qb_id = $entity->sync->qb_id ?? null;
            $number = $entity->number ?? ($entity->present()->name() ?? $entity->id);
            $notes = "{$this->entity_type} #{$number} synced to QuickBooks (QB ID: {$qb_id})";

            $activity = new Activity();
            $activity->user_id = $entity->user_id ?? null;
            $activity->company_id = $entity->company_id;
            $activity->account_id = $entity->company->account_id;
            $activity->activity_type_id = Activity::QUICKBOOKS_PUSH_SUCCESS;
            $activity->is_system = true;
            $activity->notes = $notes;

            match ($this->entity_type) {
                'client' => $activity->client_id = $entity->id,
                'invoice' => $activity->invoice_id = $entity->id,
                'payment' => $activity->payment_id = $entity->id,
                default => null,
            };

            $activity->save();
        } catch (\Throwable $e) {
            nlog("QB Batch: Failed to log success activity for {$this->entity_type} {$entity->id}: " . $e->getMessage());
        }
    }

    /**
     * Log a QuickBooks push failure as an Activity record visible to the user.
     */
    private function logActivityFailure($entity, string $errorMessage): void
    {
        try {
            $activity = new Activity();
            $activity->user_id = $entity->user_id ?? null;
            $activity->company_id = $entity->company_id;
            $activity->account_id = $entity->company->account_id;
            $activity->activity_type_id = Activity::QUICKBOOKS_PUSH_FAILURE;
            $activity->is_system = true;
            $activity->notes = str_replace('"', '', $errorMessage);

            match ($this->entity_type) {
                'client' => $activity->client_id = $entity->id,
                'invoice' => $activity->invoice_id = $entity->id,
                'payment' => $activity->payment_id = $entity->id,
                default => null,
            };

            $activity->save();
        } catch (\Throwable $e) {
            nlog("QB Batch: Failed to log activity for {$this->entity_type} {$entity->id}: " . $e->getMessage());
        }
    }

    /**
     * Resolve entities from IDs
     *
     * @return array
     */
    private function resolveEntities(): array
    {
        return match ($this->entity_type) {
            'client' => \App\Models\Client::withTrashed()->whereIn('id', $this->entity_ids)->get()->all(),
            'invoice' => \App\Models\Invoice::withTrashed()->whereIn('id', $this->entity_ids)->get()->all(),
            'product' => \App\Models\Product::withTrashed()->whereIn('id', $this->entity_ids)->get()->all(),
            'payment' => \App\Models\Payment::withTrashed()->whereIn('id', $this->entity_ids)->get()->all(),
            default => [],
        };
    }

    /**
     * Get middleware for this job
     *
     * @return array
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Handle job failure
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        nlog("QB Batch: Job failed permanently", [
            'entity_type' => $this->entity_type,
            'entity_ids' => $this->entity_ids,
            'company_id' => $this->company_id,
            'attempts' => $this->attempts(),
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        config(['queue.failed.driver' => null]);
    }
}
