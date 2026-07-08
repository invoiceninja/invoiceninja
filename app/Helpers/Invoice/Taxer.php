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
        return $this->formatValue($amount - ($amount / (1 + ($tax_rate / 100))), 2);
    }

    /**
     * Allocates the authoritative total inclusive tax (amount - net) across the
     * supplied rates so the per-rate components sum EXACTLY to $total_tax.
     * Any sub-cent rounding residual is applied to the largest component.
     *
     * @param  float $amount     gross taxable base
     * @param  array $rates      ordered list of rates (rate1, rate2, rate3...)
     * @param  float $total_tax  authoritative total = round(amount - net)
     * @param  int   $precision
     * @return array             per-rate tax components, in the same order as $rates
     */
    public function allocateInclusiveTax($amount, array $rates, $total_tax, $precision = 2)
    {
        $combined = array_sum($rates);

        $components = array_map(function ($rate) use ($amount, $combined, $precision) {
            if (!$rate || $combined <= 0) {
                return 0;
            }

            return round($amount * $rate / (100 + $combined), $precision);
        }, $rates);

        $residual = round($total_tax - array_sum($components), $precision);

        if ($residual != 0) {
            $max_index = null;

            foreach ($components as $i => $component) {
                if ($component > 0 && ($max_index === null || $component > $components[$max_index])) {
                    $max_index = $i;
                }
            }

            if ($max_index !== null) {
                $components[$max_index] = round($components[$max_index] + $residual, $precision);
            }
        }

        return $components;
    }
}
