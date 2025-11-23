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
 * Generic tax calculator for regions without specific requirements
 */
class GenericTaxCalculator implements RegionalTaxCalculator
{
    public function getHeaders(): array
    {
        return [];
    }

    public function calculateColumns(Invoice $invoice, float $amount): array
    {
        return [];
    }

    public static function supports(string $country_iso): bool
    {
        // Generic calculator supports all countries not handled by specific calculators
        return true;
    }
}
