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
use App\Models\Product;
use App\Utils\Number;

final class SalesBreakdownCalculator
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function calculate(Invoice $invoice, float $multiplier = 1.0): array
    {
        return array_map(
            fn (array $row): array => self::withPeriodAmounts($row, $multiplier),
            self::currentRows($invoice),
        );
    }

    /**
     * @param array<int, array<string, mixed>|object> $previous_rows
     * @return array<int, array<string, mixed>>
     */
    public static function calculateDelta(Invoice $invoice, array $previous_rows): array
    {
        $current = self::indexRows(self::currentRows($invoice));
        $previous = self::indexRows(self::normalizeRows($previous_rows));
        $keys = array_values(array_unique(array_merge(array_keys($current), array_keys($previous))));

        $delta = [];

        foreach ($keys as $key) {
            $current_row = $current[$key] ?? self::emptyCurrentFromPrevious($previous[$key]);
            $previous_row = $previous[$key] ?? null;

            foreach (self::amountFields() as $field) {
                $current_row[$field] = round(
                    (float) ($current_row["total_{$field}"] ?? 0) - (float) ($previous_row["total_{$field}"] ?? 0),
                    2,
                );
            }

            $delta[] = $current_row;
        }

        return $delta;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function currentRows(Invoice $invoice): array
    {
        $line_items = is_array($invoice->line_items)
            ? $invoice->line_items
            : (array) $invoice->line_items;

        $sub_total = array_sum(array_map(
            fn (object $item): float => (float) ($item->line_total ?? 0),
            $line_items,
        ));

        $buckets = [];
        $rate_format_entity = $invoice->client ?? $invoice->company;
        $uses_inclusive = (bool) ($invoice->uses_inclusive_taxes ?? false);

        foreach ($line_items as $item) {
            $line_total = (float) ($item->line_total ?? 0);

            if ($line_total == 0.0) {
                continue;
            }

            $line_amount = self::discountedAmount(
                $line_total,
                (float) ($invoice->discount ?? 0),
                (bool) ($invoice->is_amount_discount ?? false),
                $sub_total,
            );
            $components = self::taxComponents($item, $line_amount, $uses_inclusive, $rate_format_entity);
            $classification = LineClassifier::classify($item);
            $postal_code = (string) ($invoice->client->postal_code ?? '');

            if ($components === []) {
                self::push(
                    $buckets,
                    self::treatmentLabel(self::untaxedTreatment($item)),
                    0.0,
                    $classification,
                    self::untaxedTreatment($item),
                    $postal_code,
                    round($line_amount, 2),
                    0.0,
                );

                continue;
            }

            $tax_total = array_sum(array_map(fn (array $component): float => (float) $component['tax_amount'], $components));
            $sales_amount = $uses_inclusive ? round($line_amount - $tax_total, 2) : round($line_amount, 2);

            foreach ($components as $component) {
                $treatment = self::taxedTreatment($item, (float) $component['tax_rate']);

                self::push(
                    $buckets,
                    (string) $component['tax_name'],
                    (float) $component['tax_rate'],
                    $classification,
                    $treatment,
                    $postal_code,
                    $sales_amount,
                    (float) $component['tax_amount'],
                );
            }
        }

        return array_values($buckets);
    }

    /**
     * @return array<int, array{tax_name:string, tax_rate:float, tax_amount:float}>
     */
    private static function taxComponents(object $item, float $line_amount, bool $uses_inclusive, mixed $rate_format_entity): array
    {
        $components = [];

        for ($i = 1; $i <= 3; $i++) {
            $raw_tax_name = trim((string) ($item->{"tax_name{$i}"} ?? ''));
            $tax_rate = (float) ($item->{"tax_rate{$i}"} ?? 0);

            if (strlen($raw_tax_name) <= 1) {
                continue;
            }

            $tax_amount = $uses_inclusive
                ? round($line_amount - ($line_amount / (1 + ($tax_rate / 100))), 2)
                : round($line_amount * ($tax_rate / 100), 2);

            $components[] = [
                'tax_name' => self::formatTaxName($raw_tax_name, $tax_rate, $rate_format_entity),
                'tax_rate' => $tax_rate,
                'tax_amount' => $tax_amount,
            ];
        }

        return $components;
    }

    private static function push(
        array &$buckets,
        string $tax_name,
        float $tax_rate,
        string $classification,
        string $tax_treatment,
        string $postal_code,
        float $sales_amount,
        float $tax_amount,
    ): void {
        $key = implode('|', [$tax_name, $tax_rate, $classification, $tax_treatment, $postal_code]);

        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'tax_name' => $tax_name,
                'tax_rate' => $tax_rate,
                'classification' => $classification,
                'tax_treatment' => $tax_treatment,
                'postal_code' => $postal_code,
            ];

            foreach (self::amountFields() as $field) {
                $buckets[$key][$field] = 0.0;
                $buckets[$key]["total_{$field}"] = 0.0;
            }
        }

        $amounts = self::amountsForTreatment($tax_treatment, $sales_amount, $tax_amount);

        foreach (self::amountFields() as $field) {
            $buckets[$key][$field] = round((float) $buckets[$key][$field] + $amounts[$field], 2);
            $buckets[$key]["total_{$field}"] = round((float) $buckets[$key]["total_{$field}"] + $amounts[$field], 2);
        }
    }

    /**
     * @return array<string, float>
     */
    private static function amountsForTreatment(string $tax_treatment, float $sales_amount, float $tax_amount): array
    {
        return [
            'gross_sales' => $sales_amount,
            'taxable_sales' => $tax_treatment === 'taxable' ? $sales_amount : 0.0,
            'exempt_sales' => $tax_treatment === 'exempt' ? $sales_amount : 0.0,
            'non_taxable_sales' => $tax_treatment === 'non_taxable' ? $sales_amount : 0.0,
            'zero_rated_sales' => $tax_treatment === 'zero_rated' ? $sales_amount : 0.0,
            'tax_amount' => $tax_amount,
        ];
    }

    private static function withPeriodAmounts(array $row, float $multiplier): array
    {
        foreach (self::amountFields() as $field) {
            $row[$field] = round((float) $row["total_{$field}"] * $multiplier, 2);
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>|object> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeRows(array $rows): array
    {
        return array_map(fn (array|object $row): array => is_array($row) ? $row : (array) $row, $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private static function indexRows(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[self::rowKey($row)] = $row;
        }

        return $indexed;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyCurrentFromPrevious(?array $previous_row): array
    {
        $row = [
            'tax_name' => (string) ($previous_row['tax_name'] ?? ''),
            'tax_rate' => (float) ($previous_row['tax_rate'] ?? 0),
            'classification' => (string) ($previous_row['classification'] ?? ''),
            'tax_treatment' => (string) ($previous_row['tax_treatment'] ?? 'non_taxable'),
            'postal_code' => (string) ($previous_row['postal_code'] ?? ''),
        ];

        foreach (self::amountFields() as $field) {
            $row[$field] = 0.0;
            $row["total_{$field}"] = 0.0;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function rowKey(array $row): string
    {
        return implode('|', [
            (string) ($row['tax_name'] ?? ''),
            (float) ($row['tax_rate'] ?? 0),
            (string) ($row['classification'] ?? ''),
            (string) ($row['tax_treatment'] ?? ''),
            (string) ($row['postal_code'] ?? ''),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function amountFields(): array
    {
        return [
            'gross_sales',
            'taxable_sales',
            'exempt_sales',
            'non_taxable_sales',
            'zero_rated_sales',
            'tax_amount',
        ];
    }

    private static function taxedTreatment(object $item, float $tax_rate): string
    {
        $tax_id = (int) ((string) ($item->tax_id ?? '0'));

        if (in_array($tax_id, [Product::PRODUCT_TYPE_ZERO_RATED, Product::PRODUCT_TYPE_REVERSE_TAX, Product::PRODUCT_INTRA_COMMUNITY], true)) {
            return 'zero_rated';
        }

        if ($tax_id === Product::PRODUCT_TYPE_EXEMPT) {
            return 'exempt';
        }

        return $tax_rate > 0 ? 'taxable' : 'zero_rated';
    }

    private static function untaxedTreatment(object $item): string
    {
        $tax_id = (int) ((string) ($item->tax_id ?? '0'));

        return match ($tax_id) {
            Product::PRODUCT_TYPE_EXEMPT => 'exempt',
            Product::PRODUCT_TYPE_ZERO_RATED, Product::PRODUCT_TYPE_REVERSE_TAX, Product::PRODUCT_INTRA_COMMUNITY => 'zero_rated',
            default => 'non_taxable',
        };
    }

    private static function treatmentLabel(string $tax_treatment): string
    {
        return match ($tax_treatment) {
            'exempt' => 'exempt',
            'zero_rated' => 'zero rated',
            'taxable' => 'taxable',
            default => 'non-taxable',
        };
    }

    private static function discountedAmount(float $line_total, float $discount, bool $is_amount_discount, float $sub_total): float
    {
        if ($discount == 0.0) {
            return $line_total;
        }

        if ($is_amount_discount) {
            if ($sub_total == 0.0) {
                return $line_total;
            }

            return $line_total - ($line_total * ($discount / $sub_total));
        }

        return $line_total - ($line_total * ($discount / 100));
    }

    private static function formatTaxName(string $raw_tax_name, float $tax_rate, mixed $entity): string
    {
        if ($entity === null) {
            $rate_str = rtrim(rtrim(number_format($tax_rate, 10, '.', ''), '0'), '.');
        } else {
            $rate_str = Number::formatValueNoTrailingZeroes($tax_rate, $entity);
        }

        return $raw_tax_name . ' ' . $rate_str . '%';
    }
}