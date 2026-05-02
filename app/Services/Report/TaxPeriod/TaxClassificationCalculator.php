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

namespace App\Services\Report\TaxPeriod;

use App\Models\Invoice;
use App\Utils\Number;

/**
 * Walks an invoice's line items and produces tax buckets keyed by
 * (tax_name, tax_rate, classification). Mirrors InvoiceItemSum's
 * percentage / amount-discount and inclusive-tax math so the per-bucket
 * sums tie back to the aggregate tax map within rounding tolerance.
 *
 * Rounding remainders are absorbed into the largest classification within
 * each (tax_name, tax_rate) group so totals match the existing tax_details
 * snapshot exactly.
 */
final class TaxClassificationCalculator
{
    /**
     * @param  Invoice  $invoice
     * @param  float    $multiplier  Applied to taxable_amount and tax_amount
     *                               (e.g. paid_ratio for cash, -1 for deletion)
     * @param  array    $aggregate_tax_details  Existing tax_details array
     *                  (each: ['tax_name','tax_rate','taxable_amount','tax_amount',...])
     *                  Used as the source of truth to which per-classification
     *                  buckets must sum exactly.
     * @return array<int, array{tax_name:string, tax_rate:float, classification:string,
     *                          taxable_amount:float, tax_amount:float, postal_code:string}>
     */
    public static function calculate(Invoice $invoice, float $multiplier, array $aggregate_tax_details): array
    {
        $line_items = is_array($invoice->line_items)
            ? $invoice->line_items
            : (array) $invoice->line_items;

        $postal_code = (string) ($invoice->client->postal_code ?? '');
        $uses_inclusive = (bool) ($invoice->uses_inclusive_taxes ?? false);
        $discount = (float) ($invoice->discount ?? 0);
        $is_amount_discount = (bool) ($invoice->is_amount_discount ?? false);

        $sub_total = 0.0;
        foreach ($line_items as $item) {
            $sub_total += (float) ($item->line_total ?? 0);
        }

        $buckets = [];
        $line_share_by_classification = [];
        $total_share = 0.0;
        $rate_format_entity = $invoice->client ?? $invoice->company;

        foreach ($line_items as $item) {
            $line_total = (float) ($item->line_total ?? 0);
            if ($line_total == 0) {
                continue;
            }

            $classification = LineClassifier::classify($item);

            $amount = self::discountedAmount($line_total, $discount, $is_amount_discount, $sub_total);

            $line_share_by_classification[$classification] = ($line_share_by_classification[$classification] ?? 0.0) + $amount;
            $total_share += $amount;

            for ($i = 1; $i <= 3; $i++) {
                $raw_tax_name = (string) ($item->{"tax_name{$i}"} ?? '');
                $tax_rate = (float) ($item->{"tax_rate{$i}"} ?? 0);

                if (strlen($raw_tax_name) <= 1) {
                    continue;
                }

                if ($uses_inclusive) {
                    $tax_amount = round($amount - $amount / (1 + ($tax_rate / 100)), 2);
                    $taxable_amount = round($amount - $tax_amount, 2);
                } else {
                    $tax_amount = round($amount * $tax_rate / 100, 2);
                    $taxable_amount = round($amount, 2);
                }

                $tax_name = self::formatTaxName($raw_tax_name, $tax_rate, $rate_format_entity);

                self::push($buckets, $tax_name, $tax_rate, $classification, $taxable_amount, $tax_amount);
            }
        }

        if ($total_share > 0) {
            self::allocateInvoiceLevelTaxes(
                $invoice,
                $line_share_by_classification,
                $total_share,
                $uses_inclusive,
                $rate_format_entity,
                $buckets,
            );
        }

        $multiplied = self::applyMultiplier($buckets, $multiplier);
        $reconciled = self::reconcileWithAggregate($multiplied, $aggregate_tax_details);

        return self::flatten($reconciled, $postal_code);
    }

    private static function applyMultiplier(array $buckets, float $multiplier): array
    {
        if ($multiplier === 1.0) {
            return $buckets;
        }

        foreach ($buckets as $key => $bucket) {
            foreach ($bucket['children'] as $sub_key => $child) {
                $buckets[$key]['children'][$sub_key]['taxable_amount'] = round($child['taxable_amount'] * $multiplier, 2);
                $buckets[$key]['children'][$sub_key]['tax_amount'] = round($child['tax_amount'] * $multiplier, 2);
            }
        }

        return $buckets;
    }

    private static function discountedAmount(float $line_total, float $discount, bool $is_amount_discount, float $sub_total): float
    {
        if ($discount == 0) {
            return $line_total;
        }

        if ($is_amount_discount) {
            if ($sub_total == 0) {
                return $line_total;
            }
            return $line_total - ($line_total * ($discount / $sub_total));
        }

        return $line_total - ($line_total * ($discount / 100));
    }

    private static function push(array &$buckets, string $tax_name, float $tax_rate, string $classification, float $taxable_amount, float $tax_amount): void
    {
        $key = self::groupKey($tax_name, $tax_rate);
        $sub_key = $classification;

        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'tax_name' => $tax_name,
                'tax_rate' => $tax_rate,
                'children' => [],
            ];
        }

        if (!isset($buckets[$key]['children'][$sub_key])) {
            $buckets[$key]['children'][$sub_key] = [
                'taxable_amount' => 0.0,
                'tax_amount' => 0.0,
            ];
        }

        $buckets[$key]['children'][$sub_key]['taxable_amount'] += $taxable_amount;
        $buckets[$key]['children'][$sub_key]['tax_amount'] += $tax_amount;
    }

    private static function allocateInvoiceLevelTaxes(
        Invoice $invoice,
        array $line_share_by_classification,
        float $total_share,
        bool $uses_inclusive,
        $rate_format_entity,
        array &$buckets,
    ): void {
        for ($i = 1; $i <= 3; $i++) {
            $raw_tax_name = (string) ($invoice->{"tax_name{$i}"} ?? '');
            $tax_rate = (float) ($invoice->{"tax_rate{$i}"} ?? 0);

            if (strlen($raw_tax_name) < 2) {
                continue;
            }

            $tax_name = self::formatTaxName($raw_tax_name, $tax_rate, $rate_format_entity);

            $base = (float) $invoice->amount - (float) ($invoice->total_taxes ?? 0);
            if ($base <= 0) {
                continue;
            }

            if ($uses_inclusive) {
                $tax_amount = round($base - $base / (1 + ($tax_rate / 100)), 2);
                $taxable = round($base - $tax_amount, 2);
            } else {
                $tax_amount = round($base * $tax_rate / 100, 2);
                $taxable = round($base, 2);
            }

            $running_taxable = 0.0;
            $running_tax = 0.0;
            $classifications = array_keys($line_share_by_classification);
            $last = end($classifications);

            foreach ($line_share_by_classification as $classification => $share) {
                if ($classification === $last) {
                    $alloc_taxable = round($taxable - $running_taxable, 2);
                    $alloc_tax = round($tax_amount - $running_tax, 2);
                } else {
                    $ratio = $share / $total_share;
                    $alloc_taxable = round($taxable * $ratio, 2);
                    $alloc_tax = round($tax_amount * $ratio, 2);
                    $running_taxable += $alloc_taxable;
                    $running_tax += $alloc_tax;
                }

                self::push($buckets, $tax_name, $tax_rate, $classification, $alloc_taxable, $alloc_tax);
            }
        }
    }

    /**
     * Reconcile per-classification buckets to the authoritative aggregate
     * per (tax_name, tax_rate). Strategy:
     *  - If absolute deviation is larger than 1 cent per child, scale the
     *    children proportionally to match the aggregate. This handles delta /
     *    cancelled / reversed events whose aggregate is materially different
     *    from the raw line-item sum.
     *  - Otherwise (tiny rounding error), push the remainder onto the largest
     *    child so totals tie exactly.
     */
    private static function reconcileWithAggregate(array $buckets, array $aggregate_tax_details): array
    {
        foreach ($aggregate_tax_details as $detail) {
            $tax_name = (string) ($detail['tax_name'] ?? '');
            $tax_rate = (float) ($detail['tax_rate'] ?? 0);
            $aggregate_taxable = (float) ($detail['taxable_amount'] ?? 0);
            $aggregate_tax = (float) ($detail['tax_amount'] ?? 0);

            $key = self::groupKey($tax_name, $tax_rate);

            if (!isset($buckets[$key]) || empty($buckets[$key]['children'])) {
                continue;
            }

            $sum_taxable = 0.0;
            $sum_tax = 0.0;
            foreach ($buckets[$key]['children'] as $child) {
                $sum_taxable += $child['taxable_amount'];
                $sum_tax += $child['tax_amount'];
            }

            $delta_taxable = round($aggregate_taxable - $sum_taxable, 2);
            $delta_tax = round($aggregate_tax - $sum_tax, 2);

            if ($delta_taxable == 0.0 && $delta_tax == 0.0) {
                continue;
            }

            $child_count = count($buckets[$key]['children']);
            $needs_scaling = abs($delta_taxable) > $child_count || abs($delta_tax) > $child_count;

            if ($needs_scaling && $sum_taxable != 0.0) {
                $taxable_scale = $aggregate_taxable / $sum_taxable;
                $tax_scale = ($sum_tax != 0.0) ? ($aggregate_tax / $sum_tax) : 0.0;

                $running_taxable = 0.0;
                $running_tax = 0.0;
                $sub_keys = array_keys($buckets[$key]['children']);
                $last_sub = end($sub_keys);

                foreach ($buckets[$key]['children'] as $sub_key => $child) {
                    if ($sub_key === $last_sub) {
                        $buckets[$key]['children'][$sub_key]['taxable_amount'] = round($aggregate_taxable - $running_taxable, 2);
                        $buckets[$key]['children'][$sub_key]['tax_amount'] = round($aggregate_tax - $running_tax, 2);
                    } else {
                        $new_taxable = round($child['taxable_amount'] * $taxable_scale, 2);
                        $new_tax = round($child['tax_amount'] * $tax_scale, 2);
                        $buckets[$key]['children'][$sub_key]['taxable_amount'] = $new_taxable;
                        $buckets[$key]['children'][$sub_key]['tax_amount'] = $new_tax;
                        $running_taxable += $new_taxable;
                        $running_tax += $new_tax;
                    }
                }
                continue;
            }

            $largest = null;
            $largest_value = -INF;
            foreach ($buckets[$key]['children'] as $sub_key => $child) {
                if (abs($child['taxable_amount']) > $largest_value) {
                    $largest_value = abs($child['taxable_amount']);
                    $largest = $sub_key;
                }
            }

            if ($largest !== null) {
                $buckets[$key]['children'][$largest]['taxable_amount'] = round(
                    $buckets[$key]['children'][$largest]['taxable_amount'] + $delta_taxable, 2,
                );
                $buckets[$key]['children'][$largest]['tax_amount'] = round(
                    $buckets[$key]['children'][$largest]['tax_amount'] + $delta_tax, 2,
                );
            }
        }

        return $buckets;
    }

    private static function flatten(array $buckets, string $postal_code): array
    {
        $rows = [];
        foreach ($buckets as $bucket) {
            foreach ($bucket['children'] as $classification => $child) {
                $taxable = round($child['taxable_amount'], 2);
                $tax = round($child['tax_amount'], 2);
                $rows[] = [
                    'tax_name' => $bucket['tax_name'],
                    'tax_rate' => $bucket['tax_rate'],
                    'classification' => $classification,
                    'taxable_amount' => $taxable,
                    'tax_amount' => $tax,
                    'line_total' => $taxable,
                    'total_tax' => $tax,
                    'postal_code' => $postal_code,
                ];
            }
        }
        return $rows;
    }

    private static function groupKey(string $tax_name, float $tax_rate): string
    {
        return $tax_name . '|' . $tax_rate;
    }

    /**
     * Mirrors InvoiceItemSum::groupTax() suffix format so bucket names match
     * the aggregate tax_details produced by the existing InvoiceSum pipeline.
     */
    private static function formatTaxName(string $raw_tax_name, float $tax_rate, $entity): string
    {
        if ($entity === null) {
            $rate_str = rtrim(rtrim(number_format((float) $tax_rate, 10, '.', ''), '0'), '.');
        } else {
            $rate_str = Number::formatValueNoTrailingZeroes((float) $tax_rate, $entity);
        }

        return $raw_tax_name . ' ' . $rate_str . '%';
    }
}
