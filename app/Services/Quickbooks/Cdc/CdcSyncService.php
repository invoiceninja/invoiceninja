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

namespace App\Services\Quickbooks\Cdc;

use App\Models\Company;
use App\Enum\SyncDirection;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Cdc\Creators\CdcItemCreator;
use App\Services\Quickbooks\Cdc\Creators\CdcEntityCreator;
use App\Services\Quickbooks\Cdc\Creators\CdcInvoiceCreator;
use App\Services\Quickbooks\Cdc\Creators\CdcCustomerCreator;

/**
 * Orchestrates a create-only Change Data Capture pass for one company.
 *
 * Flow:
 *   1. Resolve the enabled PULL creators (customers, items, invoices).
 *   2. Resolve the changedSince watermark.
 *   3. Poll CDC once for the union of QB entity types.
 *   4. Hand each bucket to its creator, which creates only new records.
 *   5. Advance the watermark to the run-start time (minus an overlap).
 *
 * Everything is contained here and in the sibling Cdc classes. No existing
 * QuickBooks class is modified; existing create paths are only *called*.
 */
class CdcSyncService
{
    private QuickbooksService $service;

    private CdcWatermarkStore $watermarks;

    private CdcChangeFetcher $fetcher;

    public function __construct(
        public Company $company,
        ?QuickbooksService $service = null,
        ?CdcWatermarkStore $watermarks = null,
        ?CdcChangeFetcher $fetcher = null,
    ) {
        $this->service = $service ?? new QuickbooksService($company);
        $this->watermarks = $watermarks ?? new CdcWatermarkStore($company);
        $this->fetcher = $fetcher ?? new CdcChangeFetcher($this->service);
    }

    /**
     * @param ?string $since Optional first-run seed / manual override (ISO8601).
     */
    public function run(?string $since = null): CdcSyncResult
    {
        if (!$this->isConfigured()) {
            return CdcSyncResult::skipped('quickbooks_not_configured');
        }

        // Capture BEFORE the poll so records edited mid-run are re-considered next
        // pass. The create-only + qb_id existence guard makes the overlap harmless.
        $run_started_at = now();

        $creators = $this->enabledCreators();

        if ($creators === []) {
            return CdcSyncResult::skipped('no_pull_entities_enabled');
        }

        $window = $this->watermarks->resolveChangedSince($since);

        if ($window['requires_full_sync']) {
            // Watermark older than the CDC window — a delta poll would miss
            // changes. Defer to the existing full paged import instead of
            // silently under-syncing. (Left to the caller to dispatch.)
            nlog("QB CDC: watermark for company {$this->company->id} exceeds the CDC window; full sync recommended");
        }

        $buckets = $this->fetcher->fetch($this->requestedEntities($creators), $window['since']);

        $counts = [];

        // Suppress push-back so freshly created records don't echo to QB.
        QuickbooksService::$importing[$this->company->id] = true;

        try {
            foreach ($creators as $creator) {
                $records = $this->recordsFor($creator, $buckets);

                if ($records === []) {
                    continue;
                }

                $key = $creator->ninjaEntity();
                $counts[$key] = ($counts[$key] ?? 0) + $creator->createNew($records);
            }
        } finally {
            unset(QuickbooksService::$importing[$this->company->id]);
        }

        // Advance the watermark only after a clean pass.
        $this->watermarks->put(
            $run_started_at->clone()->subSeconds(CdcWatermarkStore::OVERLAP_SECONDS)->toIso8601String()
        );

        return new CdcSyncResult($counts, $window['since'], $window['requires_full_sync']);
    }

    private function isConfigured(): bool
    {
        return $this->company->quickbooks
            && !$this->company->quickbooks->requires_reconnect
            && isset($this->service->sdk);
    }

    /**
     * Creators are returned in dependency order (customers & items before
     * invoices) so an invoice can resolve its client/products locally.
     *
     * @return array<int, CdcEntityCreator>
     */
    private function enabledCreators(): array
    {
        $all = [
            new CdcCustomerCreator($this->service),
            new CdcItemCreator($this->service),
            new CdcInvoiceCreator($this->service),
        ];

        return array_values(array_filter(
            $all,
            fn (CdcEntityCreator $creator): bool => $this->service->syncable($creator->ninjaEntity(), SyncDirection::PULL)
        ));
    }

    /**
     * @param  array<int, CdcEntityCreator> $creators
     * @return array<int, string>
     */
    private function requestedEntities(array $creators): array
    {
        $entities = [];

        foreach ($creators as $creator) {
            foreach ($creator->qbEntities() as $qb_entity) {
                $entities[$qb_entity] = $qb_entity;
            }
        }

        return array_values($entities);
    }

    /**
     * Merge all CDC buckets belonging to a creator (e.g. Invoice + SalesReceipt).
     *
     * @param  array<string, array<int, mixed>> $buckets
     * @return array<int, mixed>
     */
    private function recordsFor(CdcEntityCreator $creator, array $buckets): array
    {
        $records = [];

        foreach ($creator->qbEntities() as $qb_entity) {
            foreach ($buckets[$qb_entity] ?? [] as $record) {
                $records[] = $record;
            }
        }

        return $records;
    }
}
