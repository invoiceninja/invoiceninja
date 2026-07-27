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

namespace App\Helpers\Invoice;

/**
 * Class for tax calculations.
 */
trait Taxer
{
    public function taxer($amount, $tax_rate)
    {
        if (!$tax_rate || $tax_rate == 0) {
            return 0;
        }

        return round(\App\Utils\BcMath::mul($amount, $tax_rate / 100), 2, PHP_ROUND_HALF_UP);
        // return round(($amount * (($tax_rate ? $tax_rate : 0) / 100)), 2);
    }

    public function calcAmountLineTax($tax_rate, $amount)
    {
        $tax_amount = ($amount * $tax_rate / 100);

        if ($this->peppol_enabled) {
            return $tax_amount;
        }

        return $this->formatValue(($amount * $tax_rate / 100), 2);
    }

    public function calcInclusiveLineTax($tax_rate, $amount)
    {
        // Single-rate back-out routed through the single source of truth.
        // For one rate this is identical to amount - amount/(1 + rate/100).
        return InclusiveTax::backout((float) $amount, [(float) $tax_rate], 2)['tax'];
    }

    public function reductionRatio(float $grossAmount, float $reduction): float
    {
        $grossAmount = abs($grossAmount);
        $reduction = abs($reduction);

        if ($reduction == 0) {
            return 1;
        }

        if ($grossAmount == 0) {
            return 0;
        }

        return max(0, min(1, ($grossAmount - $reduction) / $grossAmount));
    }

    public function prorateTaxEntry(array $tax, float $ratio, int $precision = 2): array
    {
        $ratio = max(0, min(1, $ratio));
        $tax['total'] = round((float) ($tax['total'] ?? 0) * $ratio, $precision);

        if (array_key_exists('base_amount', $tax)) {
            $tax['base_amount'] = round((float) $tax['base_amount'] * $ratio, $precision);
        }

        return $tax;
    }
}
