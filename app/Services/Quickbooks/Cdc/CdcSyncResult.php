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

/**
 * Immutable summary of a single CDC create-only run.
 */
class CdcSyncResult
{
    /**
     * @param array<string, int> $counts             Created count keyed by ninja entity.
     * @param ?string            $since              changedSince used for the poll.
     * @param bool               $requires_full_sync Watermark exceeded the CDC window.
     * @param bool               $skipped            Nothing ran.
     * @param ?string            $reason             Why it was skipped.
     */
    public function __construct(
        public array $counts = [],
        public ?string $since = null,
        public bool $requires_full_sync = false,
        public bool $skipped = false,
        public ?string $reason = null,
    ) {}

    public static function skipped(string $reason): self
    {
        return new self(skipped: true, reason: $reason);
    }

    public function created(): int
    {
        return array_sum($this->counts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created(),
            'counts' => $this->counts,
            'since' => $this->since,
            'requires_full_sync' => $this->requires_full_sync,
            'skipped' => $this->skipped,
            'reason' => $this->reason,
        ];
    }
}
