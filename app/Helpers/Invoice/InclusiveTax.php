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
     * @param  array<int,float>  $rates     applicable rates, e.g. [rate1, rate2, rate3]
     * @param  int               $precision currency precision
     * @return array{net: float, tax: float, components: array<int,float>}
     */
    public static function backout(float $amount, array $rates, int $precision = 2): array
    {
        $combined_rate = array_sum($rates);

        $components = array_map(function ($rate) use ($amount, $combined_rate, $precision) {
            if (!$rate || $combined_rate <= 0) {
                return 0.0;
            }

            // base x rate, algebraically equal to amount x rate / (100 + R)
            return round($amount * $rate / (100 + $combined_rate), $precision);
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
