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

namespace App\Services\EDocument\Standards\France;

use App\DataMapper\FranceEReporting\FRReportEntryData;

final readonly class FranceReportingDelta
{
    /**
     * @param array<int, FRReportEntryData> $entries
     * @param array<int, FranceReportingSubject> $snapshots
     */
    public function __construct(
        public array $entries,
        public array $snapshots,
    ) {}

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
