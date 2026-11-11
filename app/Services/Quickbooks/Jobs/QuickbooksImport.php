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

namespace App\Services\Quickbooks\Jobs;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\DataMapper\QuickbooksSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Quickbooks\SdkWrapper;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\QuickbooksRateLimiter;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class QuickbooksImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CACHE_PREFIX = 'quickbooks:initial-sync:v1';

    private const INITIAL_SYNC_PAGE_SIZE = 500;

    private array $entities = [
        'product' => 'Item',
        'client' => 'Customer',
        'invoice' => 'Invoice',
        'sales' => 'SalesReceipt',
        // 'quote' => 'Estimate',
        // 'purchase_order' => 'PurchaseOrder',
        // 'payment' => 'Payment',
        // 'vendor' => 'Vendor',
        // 'expense' => 'Purchase',
    ];

    private QuickbooksService $qbs;

    private QuickbooksSync $settings;

    private Company $company;

    public $timeout = 10800;

    public $tries = 10;

    public $maxExceptions = 3;

    public $backoff = [30, 60, 120];

    public function __construct(public int $company_id, public string $db, private ?array $syncable = []) {}

    /**
     * Execute the job.
     */
    public function handle()
    {
        MultiDB::setDb($this->db);

        $this->company = Company::query()->find($this->company_id);
        $this->qbs = new QuickbooksService($this->company);
        $this->settings =  $this->company->quickbooks->settings;

        if ($this->releaseIfRateLimited()) {
            return;
        }

        QuickbooksService::$importing[$this->company_id] = true;

        try {

            if (count($this->syncable ?? []) > 0) {
                            
                return $this->performInitialSync();
            }

            foreach ($this->entities as $key => $entity) {

                $records = $this->qbs->sdk()->fetchRecords($entity);

                $this->processEntitySync($entity, $records);

            }

        } finally {
            unset(QuickbooksService::$importing[$this->company_id]);
        }

    }

    /**
     * Processes the sync for a given entity
     *
     * @param  string $entity
     * @param  mixed $records
     * @return void
     */
    private function processEntitySync(string $entity, $records): void
    {
        match ($entity) {
            'Customer' => $this->qbs->client->syncToNinja($records),
            'Item' => $this->qbs->product->syncToNinja($records),
            'Invoice' => $this->qbs->invoice->syncToNinja($records),
            'SalesReceipt' => $this->qbs->invoice->syncToNinja($records),
            // 'vendor' => $this->syncQbToNinjaVendors($records),
            // 'quote' => $this->syncInvoices($records),
            // 'expense' => $this->syncQbToNinjaExpenses($records),
            // 'purchase_order' => $this->syncInvoices($records),
            // 'payment' => $this->syncPayment($records),
            default => false,
        };
    }


    /**
     * performInitialSync
     *
     * Performs the initial sync of the entities specified in the syncable array.
     *
     */
    private function performInitialSync()
    {
        $entities = array_values($this->syncable ?? []);

        $this->rememberInitialSyncRun($entities);

        foreach ($entities as $entity) {
            nlog('performing initial sync for ' . $entity);
            $this->performInitialSyncForEntity($entity);
        }

        nlog('performing company sync');
        //update tax rates.
        $this->qbs->companySync();

        $this->clearInitialSyncCache($entities);
    }

    private function performInitialSyncForEntity(string $entity): void
    {
        $cursor = $this->initialSyncCursor($entity);

        if (($cursor['status'] ?? null) === 'completed') {
            return;
        }

        $start_position = (int) ($cursor['start_position'] ?? 1);
        $page_size = (int) ($cursor['page_size'] ?? self::INITIAL_SYNC_PAGE_SIZE);

        $this->storeInitialSyncCursor($entity, $start_position, $page_size);

        while (true) {
            $records_from_quickbooks = $this->qbs->sdk()->fetchRecordsPage($entity, $start_position, $page_size);
            $records_from_quickbooks_count = count($records_from_quickbooks);

            if ($records_from_quickbooks_count === 0) {
                $this->completeInitialSyncCursor($entity, $page_size);

                return;
            }

            $records_to_process = $this->filterAlreadyImported($entity, $records_from_quickbooks);

            if (!empty($records_to_process)) {
                $this->processEntitySync($entity, $records_to_process);
            }

            if ($records_from_quickbooks_count < $page_size) {
                $this->completeInitialSyncCursor($entity, $page_size);

                return;
            }

            $start_position += $page_size;
            $this->storeInitialSyncCursor($entity, $start_position, $page_size);
        }
    }

    private function filterAlreadyImported(string $entity, array $records): array
    {
        $qb_ids = $this->extractQbIds($records);

        if (empty($qb_ids)) {
            return $records;
        }

        $imported_qb_ids = $this->importedQbIds($entity, $qb_ids);

        if (empty($imported_qb_ids)) {
            return $records;
        }

        return array_values(array_filter($records, function (mixed $record) use ($imported_qb_ids): bool {
            $qb_id = (string) (data_get($record, 'Id') ?? data_get($record, 'Id.value') ?? '');

            return $qb_id === '' || !isset($imported_qb_ids[$qb_id]);
        }));
    }

    /**
     * @param array<int, mixed> $records
     * @return array<int, string>
     */
    private function extractQbIds(array $records): array
    {
        $qb_ids = [];

        foreach ($records as $record) {
            $qb_id = (string) (data_get($record, 'Id') ?? data_get($record, 'Id.value') ?? '');

            if ($qb_id !== '') {
                $qb_ids[$qb_id] = $qb_id;
            }
        }

        return array_values($qb_ids);
    }

    /**
     * @param array<int, string> $qb_ids
     * @return array<string, bool>
     */
    private function importedQbIds(string $entity, array $qb_ids): array
    {
        $model_class = $this->entityModelClass($entity);

        if (!$model_class) {
            return [];
        }

        $records = $model_class::query()
            ->withTrashed()
            ->where('company_id', $this->company->id)
            ->whereIn('sync->qb_id', $qb_ids)
            ->get(['sync']);

        $imported_qb_ids = [];

        foreach ($records as $record) {
            $qb_id = (string) ($record->sync->qb_id ?? '');

            if ($qb_id !== '') {
                $imported_qb_ids[$qb_id] = true;
            }
        }

        return $imported_qb_ids;
    }

    private function entityModelClass(string $entity): ?string
    {
        return match ($entity) {
            'Customer' => Client::class,
            'Item' => Product::class,
            'Invoice', 'SalesReceipt' => Invoice::class,
            default => null,
        };
    }

    private function initialSyncCursor(string $entity): array
    {
        $cursor = Cache::get($this->initialSyncCursorCacheKey($entity));

        if (is_array($cursor)) {
            return $cursor;
        }

        return [
            'start_position' => 1,
            'page_size' => self::INITIAL_SYNC_PAGE_SIZE,
            'status' => 'running',
            'updated_at' => now()->toISOString(),
        ];
    }

    private function storeInitialSyncCursor(string $entity, int $start_position, int $page_size): void
    {
        Cache::forever($this->initialSyncCursorCacheKey($entity), [
            'start_position' => $start_position,
            'page_size' => $page_size,
            'status' => 'running',
            'updated_at' => now()->toISOString(),
        ]);
    }

    private function completeInitialSyncCursor(string $entity, int $page_size): void
    {
        $timestamp = now()->toISOString();

        Cache::forever($this->initialSyncCursorCacheKey($entity), [
            'start_position' => null,
            'page_size' => $page_size,
            'status' => 'completed',
            'updated_at' => $timestamp,
            'completed_at' => $timestamp,
        ]);
    }

    /**
     * @param array<int, string> $entities
     */
    private function rememberInitialSyncRun(array $entities): void
    {
        $existing = Cache::get($this->initialSyncRunCacheKey(), []);
        $timestamp = now()->toISOString();

        Cache::forever($this->initialSyncRunCacheKey(), [
            'status' => 'running',
            'entities' => array_values($entities),
            'started_at' => is_array($existing) ? ($existing['started_at'] ?? $timestamp) : $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @param array<int, string> $entities
     */
    private function clearInitialSyncCache(array $entities): void
    {
        Cache::forget($this->initialSyncRunCacheKey());

        foreach ($entities as $entity) {
            Cache::forget($this->initialSyncCursorCacheKey($entity));
        }
    }

    private function initialSyncRunCacheKey(): string
    {
        return $this->initialSyncCacheContext() . ':run';
    }

    private function initialSyncCursorCacheKey(string $entity): string
    {
        return $this->initialSyncCacheContext() . ":{$entity}:cursor";
    }

    private function initialSyncCacheContext(): string
    {
        $realm_id = $this->company->quickbooks->realmID ?? 'no-realm';

        return implode(':', [
            self::CACHE_PREFIX,
            $this->db,
            $this->company_id,
            $realm_id,
        ]);
    }

    // private function syncQbToNinjaInvoices($records): void
    // {


    // }



    // private function syncQbToNinjaVendors(array $records): void
    // {

    //     $transformer = new VendorTransformer($this->company);

    //     foreach($records as $record)
    //     {
    //         $ninja_data = $transformer->qbToNinja($record);

    //         if($vendor = $this->findVendor($ninja_data))
    //         {
    //             $vendor->fill($ninja_data[0]);
    //             $vendor->saveQuietly();

    //             $contact = $vendor->contacts()->where('email', $ninja_data[1]['email'])->first();

    //             if(!$contact)
    //             {
    //                 $contact = VendorContactFactory::create($this->company->id, $this->company->owner()->id);
    //                 $contact->vendor_id = $vendor->id;
    //                 $contact->send_email = true;
    //                 $contact->is_primary = true;
    //                 $contact->fill($ninja_data[1]);
    //                 $contact->saveQuietly();
    //             }
    //             elseif($this->qbs->syncable('vendor', \App\Enum\SyncDirection::PULL)){
    //                 $contact->fill($ninja_data[1]);
    //                 $contact->saveQuietly();
    //             }

    //         }

    //     }
    // }

    // private function syncQbToNinjaExpenses(array $records): void
    // {

    //     $transformer = new ExpenseTransformer($this->company);

    //     foreach($records as $record)
    //     {
    //         $ninja_data = $transformer->qbToNinja($record);

    //         if($expense = $this->findExpense($ninja_data))
    //         {
    //             $expense->fill($ninja_data);
    //             $expense->saveQuietly();
    //         }

    //     }
    // }

    // private function findExpense(array $qb_data): ?Expense
    // {
    //     $expense = $qb_data;

    //     $search = Expense::query()
    //                     ->withTrashed()
    //                     ->where('company_id', $this->company->id)
    //                     ->where('number', $expense['number']);

    //     if($search->count() == 0) {
    //         return ExpenseFactory::create($this->company->id, $this->company->owner()->id);
    //     }
    //     elseif($search->count() == 1) {
    //         return $this->qbs->syncable('expense', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
    //     }

    //     return null;
    // }

    // private function findVendor(array $qb_data) :?Vendor
    // {
    //     $vendor = $qb_data[0];
    //     $contact = $qb_data[1];
    //     $vendor_meta = $qb_data[2];

    //     $search = Vendor::query()
    //                     ->withTrashed()
    //                     ->where('company_id', $this->company->id)
    //                     ->where(function ($q) use ($vendor, $vendor_meta, $contact){

    //                         $q->where('vendor_hash', $vendor_meta['vendor_hash'])
    //                         ->orWhere('number', $vendor['number'])
    //                         ->orWhereHas('contacts', function ($q) use ($contact){
    //                             $q->where('email', $contact['email']);
    //                         });

    //                     });

    //     if($search->count() == 0) {
    //         //new client
    //         return VendorFactory::create($this->company->id, $this->company->owner()->id);
    //     }
    //     elseif($search->count() == 1) {

    //         return $this->qbs->syncable('vendor', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
    //     }

    //     return null;
    // }



    public function middleware()
    {
        return [(new WithoutOverlapping("qbs-{$this->company_id}-{$this->db}"))->expireAfter($this->timeout + 300)];
    }

    /**
     * Reuses the QuickbooksRateLimiter gate (as in BatchPushToQuickbooks): if the
     * realm is in backoff / at capacity, release the job back to the queue instead
     * of blocking a worker. The initial-sync cursor is persisted, so the retry
     * resumes exactly where it left off.
     */
    private function releaseIfRateLimited(): bool
    {
        $realm = $this->company->quickbooks->realmID ?? null;

        if (! $realm) {
            return false;
        }

        $rate_limiter = new QuickbooksRateLimiter($realm);

        if ($rate_limiter->canMakeRequest()) {
            return false;
        }

        $delay = max($rate_limiter->getRecommendedDelay(), 30);

        nlog("QuickbooksImport: no rate-limit capacity for realm {$realm}, releasing for {$delay}s");

        $this->release($delay);

        return true;
    }

    public function failed($exception): void
    {
        nlog("QuickbooksSync failed => " . $exception->getMessage());
    }
}
