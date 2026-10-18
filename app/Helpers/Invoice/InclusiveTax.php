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
 * Single source of truth for backing tax out of a tax-inclusive amount.
 *
 * Model: ADDITIVE, TAX-ANCHORED.
 *
 * When several taxes share one inclusive base they are additive (each applies
 * to the same net), so the combined rate is factored out once — never
 * compounded, never extracted independently from the full gross.
 *
 * Rounding is tax-anchored: each tax is rounded independently, and the net
 * (gross - sum(tax)) absorbs any sub-cent residual. This yields two audit-grade
 * guarantees that hold EXACTLY for every input:
 *
 *   1. net + sum(components) === gross                    (document reconciles)
 *   2. component_i === round(gross x rate_i / (100 + R))  (each tax is
 *                                                          independently
 *                                                          reproducible)
 *
 * Guarantee 2 is the auditor's reproduction rule, computed from the gross and
 * the rates alone. Note round(net x rate_i) equals component_i only within one
 * cent (it can differ at sub-cent tails), so the exact rule is stated on the
 * gross, not the net.
 *
 * There is deliberately NO inter-tax residual allocation and NO tie-break to
 * document: the net is the single, unambiguous residual sink. This mirrors the
 * exclusive path, which also rounds each tax independently.
 */
final class InclusiveTax
{
    /**
     * @param  float             $amount    gross, tax-inclusive amount
     * @param  array<int,mixed>  $rates     applicable rates, e.g. [rate1, rate2, rate3]
     * @param  int               $precision currency precision
     * @return array{net: float, tax: float, components: array<int,float>}
     */
    public static function backout(float $amount, array $rates, int $precision = 2): array
    {
        // Imported and legacy documents can contain malformed tax data. Treat an
        // invalid or non-finite rate as zero so tax calculation never turns bad
        // document data into an exception.
        $rates = array_map(static function ($rate): float {
            if (! is_numeric($rate)) {
                return 0.0;
            }

            $rate = (float) $rate;

            return is_finite($rate) ? $rate : 0.0;
        }, $rates);

        // Tax rates are stored to six decimal places. Normalize at that scale so
        // combinations that mathematically total -100 do not miss the guard due
        // to floating-point residue.
        $combined_rate = round(array_sum(array_map(
            static fn ($rate): float => round((float) $rate, 6),
            $rates
        )), 6);
        $denominator = 100.0 + $combined_rate;

        // A tax-inclusive amount cannot be backed out when the aggregate rate is
        // -100% or lower. Preserve the historical non-throwing/no-tax fallback.
        if ($denominator <= 0.0) {
            return [
                'net' => round($amount, $precision),
                'tax' => 0.0,
                'components' => array_map(static fn ($rate): float => 0.0, $rates),
            ];
        }

        $components = array_map(function ($rate) use ($amount, $denominator, $precision) {
            if (! $rate) {
                return 0.0;
            }

            // base x rate, algebraically equal to amount x rate / (100 + R)
            return round($amount * $rate / $denominator, $precision);
        }, $rates);

        $tax = round(array_sum($components), $precision);
        $net = round($amount - $tax, $precision);

        return [
            'net' => $net,
            'tax' => $tax,
            'components' => $components,
        ];
    }
}
