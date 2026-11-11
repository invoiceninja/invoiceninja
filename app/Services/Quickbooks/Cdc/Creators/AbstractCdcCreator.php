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

namespace App\Services\Quickbooks\Cdc\Creators;

use App\Services\Quickbooks\QuickbooksService;

/**
 * Base implementation shared by all CDC creators.
 *
 * Encapsulates the two rules that define this pass:
 *   1. Deleted records (CDC status="Deleted") are ignored.
 *   2. Only records with no matching sync->qb_id in Ninja are created;
 *      existing records are skipped (never updated).
 *
 * The actual persistence is delegated to persist(), which concrete creators
 * implement by calling the existing, tested QuickbooksService create paths.
 */
abstract class AbstractCdcCreator implements CdcEntityCreator
{
    public function __construct(protected QuickbooksService $service) {}

    /**
     * Fully-qualified Eloquent model class backing this entity
     * (used for the "already exists" lookup by sync->qb_id).
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    abstract protected function modelClass(): string;

    /**
     * Persist genuinely-new records. Implementations delegate to the existing
     * QuickbooksService entity handlers (e.g. $this->service->invoice->syncToNinja()).
     * Because $records is pre-filtered to new records only, these handlers act
     * purely as creators here.
     *
     * @param array<int, mixed> $records
     */
    abstract protected function persist(array $records): void;

    public function createNew(array $records): int
    {
        $candidates = $this->rejectDeleted($records);

        $new = $this->filterNew($candidates);

        if ($new === []) {
            return 0;
        }

        $this->persist($new);

        return count($new);
    }

    /**
     * Drop CDC entries flagged as deletions — out of scope for a create-only pass.
     *
     * @param  array<int, mixed> $records
     * @return array<int, mixed>
     */
    protected function rejectDeleted(array $records): array
    {
        return array_values(array_filter(
            $records,
            fn (mixed $record): bool => data_get($record, 'status') !== 'Deleted'
        ));
    }

    /**
     * Extract the QuickBooks Id from a record (handles the {"value": ".."} shape).
     */
    protected function qbId(mixed $record): string
    {
        return (string) (data_get($record, 'Id') ?? data_get($record, 'Id.value') ?? '');
    }

    /**
     * Return only the records whose QB Id is not already linked to a Ninja record.
     *
     * @param  array<int, mixed> $records
     * @return array<int, mixed>
     */
    protected function filterNew(array $records): array
    {
        $ids = [];
        foreach ($records as $record) {
            $id = $this->qbId($record);
            if ($id !== '') {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $existing = $this->existingQbIds(array_values($ids));

        return array_values(array_filter($records, function (mixed $record) use ($existing): bool {
            $id = $this->qbId($record);

            return $id !== '' && !isset($existing[$id]);
        }));
    }

    /**
     * @param  array<int, string> $qb_ids
     * @return array<string, bool> keyed by qb_id
     */
    protected function existingQbIds(array $qb_ids): array
    {
        $model_class = $this->modelClass();

        $rows = $model_class::query()
            ->withTrashed()
            ->where('company_id', $this->service->company->id)
            ->whereIn('sync->qb_id', $qb_ids)
            ->get(['sync']);

        $existing = [];

        foreach ($rows as $row) {
            $qb_id = (string) ($row->sync->qb_id ?? '');
            if ($qb_id !== '') {
                $existing[$qb_id] = true;
            }
        }

        return $existing;
    }
}
