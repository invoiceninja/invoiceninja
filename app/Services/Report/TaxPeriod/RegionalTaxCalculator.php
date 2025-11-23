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

use App\Models\Invoice;

/**
 * Base interface for region-specific tax calculations
 */
interface RegionalTaxCalculator
{
    /**
     * Get additional headers for this region
     */
    public function getHeaders(): array;

    /**
     * Calculate region-specific tax columns for an invoice
     *
     * @param Invoice $invoice
     * @param float $amount The amount to calculate taxes for
     * @return array Column values for this region
     */
    public function calculateColumns(Invoice $invoice, float $amount): array;

    /**
     * Check if this calculator should be used for the given company
     */
    public static function supports(string $country_iso): bool;
}
