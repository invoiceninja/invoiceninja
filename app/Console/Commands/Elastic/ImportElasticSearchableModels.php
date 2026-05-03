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

namespace App\Console\Commands\Elastic;

use App\Jobs\Elastic\ImportElasticSearchableChunk;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Scout\Events\ModelsImported;
use ReflectionMethod;

/**
 * Bulk-imports configured Scout models into the search engine.
 *
 * Index mappings must already exist (run `php artisan elastic:migrate` separately when
 * creating or updating Elasticsearch index definitions). This command does not drop indexes
 * or run migrations.
 *
 * {@see self::OPTION_DATABASE}: sets an explicit {@see \Illuminate\Database\Eloquent\Model::setConnection()}
 * name on import queries (this command does not call {@see \Illuminate\Support\Facades\DB::setDefaultConnection()},
 * so Laravel's configured default connection stays intact for migrations, queue tables, etc.).
 * Queued chunk jobs receive that connection name and load rows from the named connection
 * (it must exist in the worker's `config/database.php`). This does not change which MySQL
 * host credentials point at; it selects which named connection config is used.
 *
 * {@see self::OPTION_NO_QUEUE}: indexes each chunk synchronously (`searchableSync`) so Scout's
 * queue setting is bypassed for this run.
 *
 * Counts and imports always use {@see config()} `database.default` unless {@see self::OPTION_DATABASE}
 * is set. If your default connection points at an empty or minimal database, you will see
 * "No records to import" for many models; a non-default shard that holds production data will
 * show large counts for the same model types. That difference is expected.
 */
class ImportElasticSearchableModels extends Command
{
    private const LARGE_IMPORT_JOB_THRESHOLD = 20000;

    private const OPTION_DATABASE = 'database';

    private const OPTION_DRY_RUN = 'dry-run';

    private const OPTION_FORCE = 'force';

    private const OPTION_MAX_PENDING_JOBS = 'max-pending-jobs';

    private const OPTION_MODEL = 'model';

    private const OPTION_NO_QUEUE = 'no-queue';

    private const OPTION_QUEUE_SLEEP = 'queue-sleep';

    /**
     * When set, import queries and hydrated models use this connection name so queue payloads
     * include it for {@see ImportElasticSearchableChunk}.
     */
    protected ?string $importDatabaseConnection = null;

    protected $signature = 'elastic:import-all
                            {--'.self::OPTION_DATABASE.'= : Database connection name (see class PHPDoc for queued imports)}
                            {--'.self::OPTION_MODEL.'=ALL : Model basename, FQCN, index alias, comma-separated list, or ALL}
                            {--chunk=500 : Number of records to import per chunk}
                            {--'.self::OPTION_MAX_PENDING_JOBS.'=1000 : Maximum active jobs allowed on the Scout queue before dispatch pauses}
                            {--'.self::OPTION_QUEUE_SLEEP.'=5 : Seconds to sleep while the Scout queue is at capacity}
                            {--wait : Wait for queued Scout jobs to complete after each model}
                            {--'.self::OPTION_DRY_RUN.' : Show import estimates without dispatching jobs}
                            {--'.self::OPTION_FORCE.' : Allow large queued imports that exceed the safety threshold}
                            {--'.self::OPTION_NO_QUEUE.' : Import synchronously instead of queueing}';

    protected $description = 'Import all configured Scout models into the search index (run elastic:migrate separately for index setup)';

    protected array $searchableModels = [
        Client::class => 'clients',
        ClientContact::class => 'client_contacts',
        Invoice::class => 'invoices',
        Quote::class => 'quotes',
        Credit::class => 'credits',
        RecurringInvoice::class => 'recurring_invoices',
        Expense::class => 'expenses',
        PurchaseOrder::class => 'purchase_orders',
        Vendor::class => 'vendors',
        VendorContact::class => 'vendor_contacts',
        Task::class => 'tasks',
        Project::class => 'projects',
    ];

    public function handle(): int
    {
        try {
            $this->validatedPositiveIntegerOption('chunk', 'Chunk size');
            $this->validatedPositiveIntegerOption(self::OPTION_MAX_PENDING_JOBS, 'Max pending jobs');
            $this->validatedPositiveIntegerOption(self::OPTION_QUEUE_SLEEP, 'Queue sleep');
            $selectedModels = $this->resolveSearchableModelsOption();
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($database = $this->option(self::OPTION_DATABASE)) {
            if (! is_string($database) || $database === '' || ! is_array(config("database.connections.{$database}"))) {
                $this->error("Unknown database connection [{$database}].");

                return self::FAILURE;
            }

            $this->importDatabaseConnection = $database;
            $this->info("Using database connection: {$database}");
            $this->newLine();
        }

        $this->info('===========================================');
        $this->info('  Elasticsearch / Scout bulk import');
        $this->info('===========================================');
        $this->newLine();

        $totalModels = count($selectedModels);
        $currentModel = 0;
        $startTime = now();

        foreach ($selectedModels as $modelClass => $indexName) {
            ++$currentModel;
            $modelName = class_basename($modelClass);

            $this->newLine();
            $this->info("[{$currentModel}/{$totalModels}] Importing {$modelName}...");
            $this->line("Index: {$indexName}");

            if (! $this->importModel($modelClass)) {
                $this->error("Failed to import {$modelName}. Stopping.");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $duration = now()->diffForHumans($startTime, true);
        $this->info('All models imported successfully.');
        $this->info("Total time: {$duration}");

        return self::SUCCESS;
    }

    /**
     * @return array<class-string<\Illuminate\Database\Eloquent\Model>, string>
     */
    protected function resolveSearchableModelsOption(): array
    {
        $modelOption = trim((string) ($this->option(self::OPTION_MODEL) ?? 'ALL'));

        if ($modelOption === '' || strtoupper($modelOption) === 'ALL') {
            return $this->searchableModels;
        }

        $selectedModels = [];
        $requestedModels = array_filter(array_map('trim', explode(',', $modelOption)));

        foreach ($requestedModels as $requestedModel) {
            if (strtoupper($requestedModel) === 'ALL') {
                return $this->searchableModels;
            }

            $modelClass = $this->resolveSearchableModel($requestedModel);

            if ($modelClass === null) {
                throw new \InvalidArgumentException(
                    "Unknown searchable model [{$requestedModel}]. Available models: ".$this->availableSearchableModels()
                );
            }

            $selectedModels[$modelClass] = $this->searchableModels[$modelClass];
        }

        if ($selectedModels === []) {
            throw new \InvalidArgumentException('At least one model must be selected.');
        }

        return $selectedModels;
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>|null
     */
    protected function resolveSearchableModel(string $requestedModel): ?string
    {
        $normalizedRequest = strtolower(ltrim($requestedModel, '\\'));

        foreach ($this->searchableModels as $modelClass => $indexName) {
            if (
                strtolower($modelClass) === $normalizedRequest
                || strtolower(class_basename($modelClass)) === $normalizedRequest
                || strtolower($indexName) === $normalizedRequest
            ) {
                return $modelClass;
            }
        }

        return null;
    }

    protected function availableSearchableModels(): string
    {
        return collect($this->searchableModels)
            ->map(fn (string $indexName, string $modelClass): string => class_basename($modelClass))
            ->implode(', ');
    }

    protected function validatedPositiveIntegerOption(string $option, string $label): int
    {
        $value = $this->option($option);

        if (! is_numeric($value) || (int) $value < 1) {
            throw new \InvalidArgumentException("{$label} must be a positive integer.");
        }

        return (int) $value;
    }

    protected function importModel(string $modelClass): bool
    {
        $modelName = class_basename($modelClass);

        try {
            $sqlConnection = null;

            try {
                $query = $this->makeAllSearchableImportBuilder($modelClass);
                $sqlConnection = $query->getConnection()->getName();
                $this->line("SQL connection: {$sqlConnection}", 'comment');
                $recordCount = $query->count();
            } catch (\Exception $countException) {
                $this->warn('Could not count records: '.$countException->getMessage());
                $recordCount = 0;
            }

            if ($recordCount > 0) {
                try {
                    if ($this->option(self::OPTION_NO_QUEUE)) {
                        $this->line('Using synchronous import (no queue)', 'comment');
                        $this->importSynchronously($modelClass, $recordCount);
                    } else {
                        $this->importWithQueueTracking($modelClass, $recordCount);
                    }

                    $this->info("Import completed for {$recordCount} {$modelName} records.");
                } catch (\Exception $importException) {
                    $this->error('Import failed: '.$importException->getMessage());

                    return false;
                }
            } else {
                $suffix = $sqlConnection !== null ? " (connection: {$sqlConnection})" : '';
                $this->line("No records to import{$suffix}", 'comment');
            }

            return true;
        } catch (\Exception $e) {
            $this->error('Unexpected error: '.$e->getMessage());
            $this->line('Stack trace: '.$e->getTraceAsString());

            return false;
        }
    }

    protected function importSynchronously(string $modelClass, int $totalRecords): void
    {
        $chunkSize = (int) $this->option('chunk');
        $processed = 0;

        $query = $this->makeAllSearchableImportBuilder($modelClass);
        $qualifiedIdColumn = $query->qualifyColumn('id');

        $this->line("Processing chunks of {$chunkSize} records (chunkById on id)", 'comment');

        $query->chunkById($chunkSize, function ($models) use (&$processed, $totalRecords): void {
            $models->filter->shouldBeSearchable()->searchableSync();
            $processed += $models->count();
            $percentage = $totalRecords > 0 ? round(($processed / $totalRecords) * 100) : 0;
            $this->line("Indexed {$processed}/{$totalRecords} ({$percentage}%)", 'comment');
            event(new ModelsImported($models));
        }, $qualifiedIdColumn, 'id');
    }

    protected function importWithQueueTracking(string $modelClass, int $recordCount): void
    {
        $chunkSize = (int) $this->option('chunk');
        $expectedJobCount = (int) ceil($recordCount / $chunkSize);

        $queueName = $this->scoutQueueName();
        $connection = $this->scoutQueueConnection();
        $maxPendingJobs = (int) $this->option(self::OPTION_MAX_PENDING_JOBS);
        $queueSleepSeconds = (int) $this->option(self::OPTION_QUEUE_SLEEP);

        try {
            $baselineJobCount = $this->getTotalActiveJobCount($connection, $queueName);
        } catch (\Exception $e) {
            throw new \RuntimeException('Cannot track Scout queue depth: '.$e->getMessage(), previous: $e);
        }

        $this->line("Queue: {$connection}/{$queueName}", 'comment');
        $this->line("Baseline active jobs: {$baselineJobCount} (pending + processing + delayed)", 'comment');
        $this->line("Estimated import jobs: {$expectedJobCount} (chunks of {$chunkSize})", 'comment');
        $this->line("Dispatch threshold: {$maxPendingJobs} active jobs", 'comment');

        if ($this->option(self::OPTION_DRY_RUN)) {
            $this->line('Dry run: no jobs dispatched.', 'comment');

            return;
        }

        if ($expectedJobCount > self::LARGE_IMPORT_JOB_THRESHOLD && ! $this->option(self::OPTION_FORCE)) {
            throw new \RuntimeException(
                "Import would dispatch ~{$expectedJobCount} jobs. Re-run with --".self::OPTION_FORCE.' to continue.'
            );
        }

        $query = $this->makeAllSearchableImportBuilder($modelClass);
        $qualifiedIdColumn = $query->qualifyColumn('id');
        $dispatchedJobs = 0;

        $query->chunkById(
            $chunkSize,
            function ($models) use (
                &$dispatchedJobs,
                $modelClass,
                $connection,
                $queueName,
                $maxPendingJobs,
                $queueSleepSeconds,
                $expectedJobCount
            ): void {
                $this->waitForQueueCapacity($connection, $queueName, $maxPendingJobs, $queueSleepSeconds);

                ImportElasticSearchableChunk::dispatch(
                    $modelClass,
                    $this->importDatabaseConnection,
                    $models->first()->id,
                    $models->last()->id
                )->onConnection($connection)->onQueue($queueName);

                ++$dispatchedJobs;

                $this->line(
                    "Dispatched {$dispatchedJobs}/{$expectedJobCount} chunks for ".class_basename($modelClass),
                    'comment'
                );
            },
            $qualifiedIdColumn,
            'id'
        );

        $this->line("Jobs dispatched to queue: {$dispatchedJobs}", 'comment');

        if ($this->option('wait')) {
            $this->waitForOurJobsToComplete($connection, $queueName, $baselineJobCount, $expectedJobCount);
        }
    }

    protected function scoutQueueConnection(): string
    {
        $scoutQueue = config('scout.queue');

        if (is_array($scoutQueue) && ! empty($scoutQueue['connection'])) {
            return (string) $scoutQueue['connection'];
        }

        return (string) config('queue.default');
    }

    protected function scoutQueueName(): string
    {
        $scoutQueue = config('scout.queue');

        if (is_array($scoutQueue) && ! empty($scoutQueue['queue'])) {
            return (string) $scoutQueue['queue'];
        }

        return (string) config("queue.connections.{$this->scoutQueueConnection()}.queue", 'default');
    }

    protected function waitForQueueCapacity(
        string $connection,
        string $queueName,
        int $maxPendingJobs,
        int $queueSleepSeconds
    ): void {
        while (true) {
            $activeJobs = $this->getTotalActiveJobCount($connection, $queueName);

            if ($activeJobs < $maxPendingJobs) {
                return;
            }

            $this->line(
                "Queue [{$connection}/{$queueName}] has {$activeJobs} active jobs; waiting for capacity below {$maxPendingJobs}.",
                'comment'
            );

            sleep($queueSleepSeconds);
        }
    }

    protected function waitForOurJobsToComplete(
        string $connection,
        string $queueName,
        int $baselineJobCount,
        int $expectedJobCount
    ): void {
        $this->newLine();
        $this->line("Waiting for our {$expectedJobCount} jobs to complete...");
        $this->line('(Tracking: pending + processing + delayed jobs)', 'comment');

        $lastReportedDelta = -1;

        while (true) {
            try {
                $currentJobCount = $this->getTotalActiveJobCount($connection, $queueName);
                $delta = $currentJobCount - $baselineJobCount;

                if ($currentJobCount <= $baselineJobCount) {
                    $this->info("Our jobs completed (active: {$currentJobCount}, baseline: {$baselineJobCount})");

                    return;
                }

                if ($delta !== $lastReportedDelta) {
                    $this->line("Our jobs remaining: ~{$delta} (total active: {$currentJobCount})", 'comment');
                    $lastReportedDelta = $delta;
                }

                sleep(2);
            } catch (\Exception $e) {
                throw new \RuntimeException('Could not check queue status while waiting: '.$e->getMessage(), previous: $e);
            }
        }
    }

    protected function getTotalActiveJobCount(string $connection, string $queueName): int
    {
        $driver = config("queue.connections.{$connection}.driver");

        switch ($driver) {
            case 'database':
                return DB::table(config("queue.connections.{$connection}.table", 'jobs'))
                    ->where('queue', $queueName)
                    ->count();

            case 'redis':
                $redisConnection = config("queue.connections.{$connection}.connection", 'default');
                $redis = Redis::connection($redisConnection);

                $pending = $redis->llen('queues:'.$queueName);
                $processing = $redis->zcard('queues:'.$queueName.':reserved');
                $delayed = $redis->zcard('queues:'.$queueName.':delayed');

                return $pending + $processing + $delayed;

            case 'sync':
                return 0;

            default:
                throw new \Exception("Cannot check queue size for driver: {$driver}");
        }
    }

    /**
     * Mirror {@see \Laravel\Scout\Searchable::makeAllSearchableQuery()} so imports use the same
     * query constraints as {@see \Laravel\Scout\Console\ImportCommand}, with an optional
     * explicit Eloquent connection for queued chunk jobs.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    protected function makeAllSearchableImportBuilder(string $modelClass): EloquentBuilder
    {
        $model = new $modelClass;

        if ($this->importDatabaseConnection !== null) {
            $model->setConnection($this->importDatabaseConnection);
        }

        $softDelete = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            && config('scout.soft_delete', false);

        $query = $model->newQuery();

        $makeAllSearchableUsing = new ReflectionMethod($modelClass, 'makeAllSearchableUsing');
        $makeAllSearchableUsing->setAccessible(true);
        $query = $makeAllSearchableUsing->invoke($model, $query);

        if ($softDelete) {
            $query->withTrashed();
        }

        return $query->orderBy(
            $model->qualifyColumn('id')
        );
    }
}
