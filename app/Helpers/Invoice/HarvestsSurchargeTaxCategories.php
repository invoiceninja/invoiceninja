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

use Illuminate\Support\Collection;

/**
 * Resolves which VAT categories apply to taxed document surcharges.
 *
 * Invoice-level: all named header taxes (tax_name1–3) apply together.
 * Line fallback: taxes from the first line item only.
 */
trait HarvestsSurchargeTaxCategories
{
    protected function isPeppolClient(): bool
    {
        return $this->client->getSetting('e_invoice_type') === 'PEPPOL';
    }

    /**
     * PEPPOL clients tax every surcharge; others honour per-slot custom_surcharge_taxN flags.
     */
    protected function shouldTaxSurcharge(int $index): bool
    {
        $amount = $this->invoice->{"custom_surcharge{$index}"};

        if (! is_numeric($amount) || $amount <= 0) {
            return false;
        }

        if ($this->isPeppolClient()) {
            return true;
        }

        return (bool) $this->invoice->{"custom_surcharge_tax{$index}"};
    }

    protected function hasSurchargesRequiringTaxAllocation(): bool
    {
        foreach ([1, 2, 3, 4] as $i) {
            if ($this->shouldTaxSurcharge($i)) {
                return true;
            }
        }

        return false;
    }

    protected function hasInvoiceLevelTaxCategories(): bool
    {
        foreach ([1, 2, 3] as $i) {
            if (strlen($this->invoice->{"tax_name{$i}"} ?? '') > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array{name: string, percentage: float, tax_id: string}>
     */
    protected function harvestSurchargeTaxCategories(): Collection
    {
        $invoiceCategories = $this->collectTaxCategoriesFromInvoice();

        if ($invoiceCategories->isNotEmpty()) {
            return $invoiceCategories;
        }

        return $this->collectTaxCategoriesFromFirstLineItem();
    }

    /**
     * @return Collection<int, array{name: string, percentage: float, tax_id: string}>
     */
    private function collectTaxCategoriesFromInvoice(): Collection
    {
        return collect([1, 2, 3])
            ->map(fn (int $i) => [
                'name' => $this->invoice->{"tax_name{$i}"} ?? '',
                'percentage' => (float) ($this->invoice->{"tax_rate{$i}"} ?? 0),
                'tax_id' => '1',
            ])
            ->filter(fn (array $tax) => strlen($tax['name']) > 1)
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, percentage: float, tax_id: string}>
     */
    private function collectTaxCategoriesFromFirstLineItem(): Collection
    {
        $first = collect($this->invoice->line_items)->first();

        if (! $first) {
            return collect();
        }

        return collect([1, 2, 3])
            ->map(fn (int $i) => [
                'name' => $first->{"tax_name{$i}"} ?? '',
                'percentage' => (float) ($first->{"tax_rate{$i}"} ?? 0),
                'tax_id' => $first->tax_id ?? '1',
            ])
            ->filter(fn (array $tax) => strlen($tax['name']) > 1)
            ->values();
    }
}
