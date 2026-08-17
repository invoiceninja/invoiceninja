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

namespace App\Services\PdfMaker;

use App\Exceptions\BatchPdfException;
use App\Jobs\Invoice\PrintEntityBatch;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class BatchPdfService
{
    private const CACHE_TTL_MINUTES = 60;

    private const POLL_INTERVAL_MICROSECONDS = 300000;

    /**
     * @param class-string $entity_class
     * @param array<int, int> $entity_ids
     * @throws BatchPdfException
     */
    public function render(string $entity_class, array $entity_ids, string $db): string
    {
        $cache_keys = [];

        try {
            if ($entity_ids === []) {
                throw new RuntimeException('Batch PDF rendering requires at least one entity.');
            }

            $batch_id = (new PrintEntityBatch($entity_class, $entity_ids, $db))->handle();
            $batch = $this->findBatch($batch_id);
            $batch_key = (string) $batch->name;
            $cache_keys = array_map(
                fn (int $entity_id): string => $this->cacheKey($batch_key, $entity_id),
                $entity_ids,
            );

            while (! $batch->finished() && ! $batch->cancelled()) {
                usleep(self::POLL_INTERVAL_MICROSECONDS);
                $batch = $this->findBatch($batch_id);
            }

            if ($batch->cancelled() || $batch->hasFailures()) {
                throw new RuntimeException("Batch PDF rendering failed for batch {$batch_id}.");
            }

            $pdfs = array_map(function (string $cache_key): string {
                $pdf = Cache::get($cache_key);

                if (! is_string($pdf) || $pdf === '') {
                    throw new RuntimeException("Batch PDF result is missing for cache key {$cache_key}.");
                }

                return $pdf;
            }, $cache_keys);

            return (string) (new PdfMerge($pdfs))->run();
               
        } catch (Throwable $exception) {
            throw new BatchPdfException('Unable to generate the batch PDF.', previous: $exception);
        } finally {
            foreach ($cache_keys as $cache_key) {
                Cache::forget($cache_key);
            }
        }

    }

    /**
     * @throws BatchPdfException
     */
    public function cachePdf(string $cache_key, string $pdf): void
    {
        try {
            if (! Cache::put($cache_key, $pdf, now()->addMinutes(self::CACHE_TTL_MINUTES))) {
                throw new RuntimeException("Unable to cache batch PDF for key {$cache_key}.");
            }
        } catch (Throwable $exception) {
            throw new BatchPdfException('Unable to cache the batch PDF.', previous: $exception);
        }
    }

    private function findBatch(string $batch_id): Batch
    {
        $batch = Bus::findBatch($batch_id);

        if (! $batch) {
            throw new RuntimeException("Batch PDF record {$batch_id} was not found.");
        }

        return $batch;
    }

    private function cacheKey(string $batch_key, int $entity_id): string
    {
        return "{$batch_key}-{$entity_id}";
    }
}
