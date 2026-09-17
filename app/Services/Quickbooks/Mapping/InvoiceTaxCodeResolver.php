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

namespace App\Services\Quickbooks\Mapping;

use App\Exceptions\QuickbooksMissingTaxCode;
use App\Models\Invoice;
use App\Services\Quickbooks\TaxCodeComponentKey;

final class InvoiceTaxCodeResolver
{
    public function resolveForLine(object $line_item, TaxExportContext $context): string
    {
        if (isset($line_item->tax_id) && in_array($line_item->tax_id, ['5', '8'])) {
            return $context->exempt_code;
        }

        if ($context->automatic_taxes) {
            return $context->taxable_code;
        }

        if ($context->isUs()) {
            return $this->resolveLineTaxCodeUS($line_item, $context->taxable_code, $context->exempt_code);
        }

        return $this->resolveLineTaxCode(
            $line_item,
            $context->tax_rate_map,
            $context->composite_tax_code_map,
            $context->taxable_code,
            $context->exempt_code
        );
    }

    /**
     * @return array<string, string|float>
     */
    public function extractInvoiceLevelTaxes(Invoice $invoice): array
    {
        $taxes = [];

        foreach ([1, 2, 3] as $i) {
            $name = $invoice->{"tax_name{$i}"};

            if (is_string($name) && strlen($name) > 1) {
                $taxes["tax_name{$i}"] = $name;
                $taxes["tax_rate{$i}"] = $invoice->{"tax_rate{$i}"};
            }
        }

        return $taxes;
    }

    public function mergeInvoiceLevelTaxes(object $line_item, array $invoice_level_taxes): object
    {
        if (empty($invoice_level_taxes)) {
            return $line_item;
        }

        $merged = clone $line_item;

        foreach ($invoice_level_taxes as $key => $value) {
            $merged->{$key} = $value;
        }

        return $merged;
    }

    /**
     * @return array<string, array<int, array{name: string, rate: float}>>
     */
    public function unresolvedTaxCodeComponents(Invoice $invoice, array $invoice_level_taxes, array $tax_rate_map, array $composite_tax_code_map): array
    {
        $missing_components = [];

        foreach ($invoice->line_items as $line_item) {
            if (isset($line_item->tax_id) && in_array((string) $line_item->tax_id, ['5', '8'], true)) {
                continue;
            }

            $line_item = $this->mergeInvoiceLevelTaxes($line_item, $invoice_level_taxes);
            $components = $this->taxComponentsFromLineItem($line_item);

            if (empty($components)) {
                continue;
            }

            $component_key = TaxCodeComponentKey::fromComponents($components);

            if ($component_key === '') {
                continue;
            }

            if (count($components) === 1 && $this->findTaxCodeIdByRate($tax_rate_map, $components[0]['rate'], $components[0]['name']) === null) {
                $missing_components[$component_key] = $components;
                continue;
            }

            if (count($components) > 1 && $this->findCompositeTaxCodeId($components, $composite_tax_code_map) === null) {
                $missing_components[$component_key] = $components;
            }
        }

        return $missing_components;
    }

    /**
     * @param  array<int, mixed>  $tax_rate_map
     * @param  array<string, mixed>  $composite_tax_code_map
     */
    public function resolveLineTaxCode(object $line_item, array $tax_rate_map, array $composite_tax_code_map, string $taxable_code, string $exempt_code): string
    {
        $components = $this->taxComponentsFromLineItem($line_item);

        if (empty($components)) {
            return $exempt_code;
        }

        if (count($components) === 1) {
            $tax_code_id = $this->findTaxCodeIdByRate($tax_rate_map, $components[0]['rate'], $components[0]['name']);

            if ($tax_code_id) {
                return $tax_code_id;
            }

            nlog('QB: no TaxCode for invoice tax; invoice push blocked', [
                'components' => $components,
            ]);

            throw QuickbooksMissingTaxCode::forComponents($components);
        }

        $tax_code_id = $this->findCompositeTaxCodeId($components, $composite_tax_code_map);

        if ($tax_code_id) {
            return $tax_code_id;
        }

        nlog('QB: no composite TaxCode for combined invoice taxes; invoice push blocked', [
            'components' => $components,
        ]);

        throw QuickbooksMissingTaxCode::forComponents($components);
    }

    /**
     * @return array<int, array{name: string, rate: float}>
     */
    public function taxComponentsFromLineItem(object $line_item): array
    {
        $components = [];

        foreach ([1, 2, 3] as $index) {
            $rate = (float) ($line_item->{"tax_rate{$index}"} ?? 0);

            if ($rate <= 0) {
                continue;
            }

            $components[] = [
                'name' => trim((string) ($line_item->{"tax_name{$index}"} ?? '')),
                'rate' => $rate,
            ];
        }

        return $components;
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     * @param  array<string, mixed>  $composite_tax_code_map
     */
    public function findCompositeTaxCodeId(array $components, array $composite_tax_code_map): ?string
    {
        $component_key = TaxCodeComponentKey::fromComponents($components);
        $candidates = $composite_tax_code_map[$component_key] ?? [];

        if (is_string($candidates)) {
            return $candidates;
        }

        if (!is_array($candidates) || empty($candidates)) {
            return null;
        }

        if (isset($candidates['tax_code_id'])) {
            $candidates = [$candidates];
        }

        $candidate_ids = [];

        foreach ($candidates as $candidate) {
            $candidate_id = is_array($candidate) ? (string) ($candidate['tax_code_id'] ?? '') : (string) $candidate;

            if ($candidate_id !== '') {
                $candidate_ids[] = $candidate_id;
            }
        }

        $candidate_ids = array_values(array_unique($candidate_ids));

        if (count($candidate_ids) === 1) {
            return $candidate_ids[0];
        }

        if (count($candidate_ids) > 1) {
            nlog('QB: ambiguous composite TaxCode for combined invoice taxes; invoice push blocked', [
                'component_key' => $component_key,
                'candidates' => $candidates,
            ]);
        }

        return null;
    }

    public function resolveLineTaxCodeUS(object $line_item, string $taxable_code, string $exempt_code): string
    {
        $has_line_tax = (
            (isset($line_item->tax_rate1) && $line_item->tax_rate1 > 0)
            || (isset($line_item->tax_rate2) && $line_item->tax_rate2 > 0)
            || (isset($line_item->tax_rate3) && $line_item->tax_rate3 > 0)
        );

        return $has_line_tax ? $taxable_code : $exempt_code;
    }

    /**
     * @param  array<int, mixed>  $tax_rate_map
     */
    public function findTaxCodeIdByRate(array $tax_rate_map, float $rate, string $name): ?string
    {
        $rate_only_match = null;

        foreach ($tax_rate_map as $entry) {
            if (empty($entry['tax_code_id']) || TaxCodeComponentKey::formatRate($entry['rate'] ?? 0) !== TaxCodeComponentKey::formatRate($rate)) {
                continue;
            }

            $entry_name = (string) ($entry['name'] ?? '');

            if ($name === '' || $entry_name === '' || stripos($name, $entry_name) !== false || stripos($entry_name, $name) !== false) {
                return $entry['tax_code_id'];
            }

            $rate_only_match ??= $entry['tax_code_id'];
        }

        return $rate_only_match;
    }

    /**
     * @param  array<int, mixed>  $tax_rate_map
     */
    public function findTaxRateIdByRateAndName(array $tax_rate_map, float $rate, string $name): ?string
    {
        foreach ($tax_rate_map as $entry) {
            if (floatval($entry['rate']) == $rate && $entry['name'] == $name) {
                return $entry['id'];
            }
        }

        return null;
    }
}
