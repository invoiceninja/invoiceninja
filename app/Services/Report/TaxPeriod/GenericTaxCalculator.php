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

    public function reportingBucket(Invoice $invoice, TaxDetail $tax_detail): string
    {
        return implode(' | ', array_filter([
            'Generic',
            $this->postalCode($invoice, $tax_detail),
            $this->taxLabel($tax_detail),
            $tax_detail->classification ?: ctrans('texts.unknown'),
        ], fn (string $part): bool => $part !== ''));
    }

    public function jurisdictionSource(Invoice $invoice, TaxDetail $tax_detail): string
    {
        if (trim($tax_detail->postal_code) !== '') {
            return ctrans('texts.tax_detail_source');
        }

        if (trim((string) ($invoice->client->shipping_postal_code ?? '')) !== '') {
            return ctrans('texts.client_shipping_source');
        }

        if (trim((string) ($invoice->client->postal_code ?? '')) !== '') {
            return ctrans('texts.client_billing_source');
        }

        return ctrans('texts.unknown_source');
    }

    public static function supports(string $country_iso): bool
    {
        // Generic calculator supports all countries not handled by specific calculators
        return true;
    }

    private function postalCode(Invoice $invoice, TaxDetail $tax_detail): string
    {
        return trim((string) ($tax_detail->postal_code
            ?: $invoice->client->shipping_postal_code
            ?: $invoice->client->postal_code
            ?: ''));
    }

    private function taxLabel(TaxDetail $tax_detail): string
    {
        $tax_name = trim($tax_detail->tax_name);
        $tax_rate = $this->formatPercent($tax_detail->tax_rate);

        if ($tax_name === '') {
            return $tax_rate;
        }

        if ($tax_rate !== '' && !str_contains($tax_name, $tax_rate)) {
            return trim("{$tax_name} {$tax_rate}");
        }

        return $tax_name;
    }

    private function formatPercent(float $rate): string
    {
        $formatted = rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.');

        return $formatted === '' ? '' : $formatted . '%';
    }
}
