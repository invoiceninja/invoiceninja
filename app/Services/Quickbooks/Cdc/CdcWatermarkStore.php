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

use Carbon\Carbon;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;

/**
 * Per-company "changed since" watermark for CDC polling.
 *
 * Stored in the cache (Cache::forever) — mirrors the initial-sync cursor
 * pattern — so this feature needs no schema change and touches no existing
 * settings object.
 */
class CdcWatermarkStore
{
    private const PREFIX = 'quickbooks:cdc-sync:v1';

    /** Re-scan overlap subtracted from the run-start time before persisting. */
    public const OVERLAP_SECONDS = 120;

    /** QuickBooks rejects/truncates a CDC changedSince older than ~30 days. */
    public const MAX_LOOKBACK_DAYS = 29;

    /** Seed window used the very first time CDC runs for a company. */
    public const DEFAULT_SEED_HOURS = 24;

    public function __construct(private Company $company) {}

    public function get(): ?string
    {
        $value = Cache::get($this->key());

        return is_string($value) ? $value : null;
    }

    public function put(string $iso8601): void
    {
        Cache::forever($this->key(), $iso8601);
    }

    public function forget(): void
    {
        Cache::forget($this->key());
    }

    /**
     * Resolve the changedSince timestamp for the next CDC call.
     *
     * @param  ?string $override Explicit ISO8601 override (e.g. first-run seed).
     * @return array{since: string, requires_full_sync: bool}
     *         requires_full_sync is true when the watermark is older than the
     *         30-day CDC window — the caller should fall back to a full pull to
     *         avoid a coverage gap.
     */
    public function resolveChangedSince(?string $override = null): array
    {
        $candidate = $override ?? $this->get();

        $since = $candidate
            ? Carbon::parse($candidate)
            : now()->subHours(self::DEFAULT_SEED_HOURS);

        $floor = now()->subDays(self::MAX_LOOKBACK_DAYS);
        $requires_full_sync = false;

        if ($since->lt($floor)) {
            $since = $floor;
            $requires_full_sync = true;
        }

        return [
            'since' => $since->toIso8601String(),
            'requires_full_sync' => $requires_full_sync,
        ];
    }

    private function key(): string
    {
        $realm = $this->company->quickbooks->realmID ?? 'no-realm';

        return implode(':', [self::PREFIX, $this->company->db, $this->company->id, $realm, 'watermark']);
    }
}
