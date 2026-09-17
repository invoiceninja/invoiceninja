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

namespace Tests\Unit\Services\PdfMaker;

use Mockery;
use Carbon\CarbonImmutable;
use RuntimeException;
use Tests\TestCase;
use App\Jobs\Invoice\PrintEntityBatch;
use App\Models\Invoice;
use App\Exceptions\BatchPdfException;
use App\Services\PdfMaker\PdfMerge;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use App\Services\PdfMaker\BatchPdfService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class BatchPdfServiceTest extends TestCase
{
    public function testRenderExposesBatchPdfExceptionForInvalidInput(): void
    {
        try {
            (new BatchPdfService())->render(Invoice::class, [], 'db-ninja-01');
        } catch (BatchPdfException $exception) {
            $this->assertSame('Unable to generate the batch PDF.', $exception->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());

            return;
        }

        $this->fail('A BatchPdfException was not thrown.');
    }

    public function testCachePdfUsesAnExpiringCacheEntry(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->with(
                'batch-key',
                'pdf-content',
                Mockery::on(fn ($expiration): bool => $expiration->isBetween(
                    now()->addMinutes(59),
                    now()->addMinutes(61),
                )),
            )
            ->andReturnTrue();

        (new BatchPdfService())->cachePdf('batch-key', 'pdf-content');
    }

    public function testCachePdfExposesBatchPdfExceptionWhenCachingFails(): void
    {
        Cache::shouldReceive('put')->once()->andReturnFalse();

        try {
            (new BatchPdfService())->cachePdf('batch-key', 'pdf-content');
        } catch (BatchPdfException $exception) {
            $this->assertSame('Unable to cache the batch PDF.', $exception->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());

            return;
        }

        $this->fail('A BatchPdfException was not thrown.');
    }

    public function testCachePdfWrapsCacheStoreExceptions(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->andThrow(new RuntimeException('Cache unavailable.'));

        try {
            (new BatchPdfService())->cachePdf('batch-key', 'pdf-content');
        } catch (BatchPdfException $exception) {
            $this->assertSame('Unable to cache the batch PDF.', $exception->getMessage());
            $this->assertSame('Cache unavailable.', $exception->getPrevious()?->getMessage());

            return;
        }

        $this->fail('A BatchPdfException was not thrown.');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRenderPollsUntilFinishedMergesInEntityOrderAndCleansCache(): void
    {
        $this->mockBatchLauncher('batch-id');

        Bus::shouldReceive('findBatch')
            ->twice()
            ->with('batch-id')
            ->andReturn(
                $this->batch(pending_jobs: 2),
                $this->batch(pending_jobs: 0, finished: true),
            );

        Cache::shouldReceive('get')->once()->with('batch-key-20')->andReturn('second-pdf');
        Cache::shouldReceive('get')->once()->with('batch-key-10')->andReturn('first-pdf');
        Cache::shouldReceive('forget')->once()->with('batch-key-20')->andReturnTrue();
        Cache::shouldReceive('forget')->once()->with('batch-key-10')->andReturnTrue();

        $pdf_merge = Mockery::mock('overload:' . PdfMerge::class);
        $pdf_merge->shouldReceive('__construct')->once()->with(['second-pdf', 'first-pdf']);
        $pdf_merge->shouldReceive('run')->once()->andReturn('merged-pdf');

        $pdf = (new BatchPdfService())->render(
            Invoice::class,
            [20, 10],
            'db-ninja-01',
        );

        $this->assertSame('merged-pdf', $pdf);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRenderRejectsCancelledBatchAndCleansCache(): void
    {
        $this->mockBatchLauncher('cancelled-batch-id');

        Bus::shouldReceive('findBatch')
            ->once()
            ->with('cancelled-batch-id')
            ->andReturn($this->batch(cancelled: true));
        Cache::shouldReceive('forget')->once()->with('batch-key-10')->andReturnTrue();

        $this->assertRenderFailure(
            fn (): string => (new BatchPdfService())->render(Invoice::class, [10], 'db-ninja-01'),
            'Batch PDF rendering failed for batch cancelled-batch-id.',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRenderRejectsCompletedBatchWithFailuresAndCleansCache(): void
    {
        $this->mockBatchLauncher('failed-batch-id');

        Bus::shouldReceive('findBatch')
            ->once()
            ->with('failed-batch-id')
            ->andReturn($this->batch(pending_jobs: 0, failed_jobs: 1, finished: true));
        Cache::shouldReceive('forget')->once()->with('batch-key-10')->andReturnTrue();

        $this->assertRenderFailure(
            fn (): string => (new BatchPdfService())->render(Invoice::class, [10], 'db-ninja-01'),
            'Batch PDF rendering failed for batch failed-batch-id.',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRenderRejectsMissingCachedPdfAndCleansEveryCacheKey(): void
    {
        $this->mockBatchLauncher('batch-id');

        Bus::shouldReceive('findBatch')
            ->once()
            ->with('batch-id')
            ->andReturn($this->batch(pending_jobs: 0, finished: true));
        Cache::shouldReceive('get')->once()->with('batch-key-10')->andReturn('first-pdf');
        Cache::shouldReceive('get')->once()->with('batch-key-20')->andReturnNull();
        Cache::shouldReceive('forget')->once()->with('batch-key-10')->andReturnTrue();
        Cache::shouldReceive('forget')->once()->with('batch-key-20')->andReturnTrue();

        $this->assertRenderFailure(
            fn (): string => (new BatchPdfService())->render(Invoice::class, [10, 20], 'db-ninja-01'),
            'Batch PDF result is missing for cache key batch-key-20.',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRenderWrapsMissingBatchRecord(): void
    {
        $this->mockBatchLauncher('missing-batch-id');

        Bus::shouldReceive('findBatch')
            ->once()
            ->with('missing-batch-id')
            ->andReturnNull();

        $this->assertRenderFailure(
            fn (): string => (new BatchPdfService())->render(Invoice::class, [10], 'db-ninja-01'),
            'Batch PDF record missing-batch-id was not found.',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRenderWrapsMergeFailureAndCleansCache(): void
    {
        $this->mockBatchLauncher('batch-id');

        Bus::shouldReceive('findBatch')
            ->once()
            ->with('batch-id')
            ->andReturn($this->batch(pending_jobs: 0, finished: true));
        Cache::shouldReceive('get')->once()->with('batch-key-10')->andReturn('pdf-content');
        Cache::shouldReceive('forget')->once()->with('batch-key-10')->andReturnTrue();

        $pdf_merge = Mockery::mock('overload:' . PdfMerge::class);
        $pdf_merge->shouldReceive('run')
            ->once()
            ->andThrow(new RuntimeException('Unable to merge PDFs.'));

        $this->assertRenderFailure(
            fn (): string => (new BatchPdfService())->render(Invoice::class, [10], 'db-ninja-01'),
            'Unable to merge PDFs.',
        );
    }

    private function mockBatchLauncher(string $batch_id): void
    {
        $print_entity_batch = Mockery::mock('overload:' . PrintEntityBatch::class);
        $print_entity_batch->shouldReceive('handle')->once()->andReturn($batch_id);
    }

    private function batch(
        int $pending_jobs = 1,
        int $failed_jobs = 0,
        bool $cancelled = false,
        bool $finished = false,
    ): Batch {
        return new Batch(
            Mockery::mock(QueueFactory::class),
            Mockery::mock(BatchRepository::class),
            'batch-id',
            'batch-key',
            max($pending_jobs + $failed_jobs, 1),
            $pending_jobs,
            $failed_jobs,
            [],
            [],
            CarbonImmutable::now(),
            $cancelled ? CarbonImmutable::now() : null,
            $finished ? CarbonImmutable::now() : null,
        );
    }

    /**
     * @param callable(): string $callback
     */
    private function assertRenderFailure(callable $callback, string $previous_message): void
    {
        try {
            $callback();
        } catch (BatchPdfException $exception) {
            $this->assertSame('Unable to generate the batch PDF.', $exception->getMessage());
            $this->assertSame($previous_message, $exception->getPrevious()?->getMessage());

            return;
        }

        $this->fail('A BatchPdfException was not thrown.');
    }
}
