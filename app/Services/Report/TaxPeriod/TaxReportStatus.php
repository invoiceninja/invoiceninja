<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Report\TaxPeriod;

/**
 * Enum for tax report status values
 */
enum TaxReportStatus: string
{
    case UPDATED = 'updated';
    case DELTA = 'delta';
    case ADJUSTMENT = 'adjustment';
    case CANCELLED = 'cancelled';
    case DELETED = 'deleted';
    case RESTORED = 'restored';
    case REVERSED = 'reversed';

    /**
     * Get human-readable label for the status
     */
    public function label(): string
    {
        return match($this) {
            self::UPDATED => 'payable',
            self::DELTA => 'payable',
            self::ADJUSTMENT => 'adjustment',
            self::CANCELLED => 'cancelled',
            self::DELETED => 'deleted',
            self::RESTORED => 'restored',
            self::REVERSED => 'reversed',
        };
    }

    /**
     * Check if this status represents a payable amount
     */
    public function isPayable(): bool
    {
        return in_array($this, [self::UPDATED, self::DELTA]);
    }
}
