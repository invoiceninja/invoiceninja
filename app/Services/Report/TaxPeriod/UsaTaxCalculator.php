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
 * USA-specific tax calculator with state, county, city, and district breakdowns
 */
class UsaTaxCalculator implements RegionalTaxCalculator
{
    public function getHeaders(): array
    {
        return [
            'State',
            'State Tax Rate',
            'State Tax Amount',
            'County',
            'County Tax Rate',
            'County Tax Amount',
            'City',
            'City Tax Rate',
            'City Tax Amount',
            'District Tax Rate',
            'District Tax Amount',
        ];
    }

    /**
     * Calculate USA-specific tax breakdown
     *
     * Proportionally allocates the given amount across state/county/city/district
     * based on the invoice's tax_data breakdown
     *
     * @param Invoice $invoice
     * @param float $amount The tax amount to allocate
     * @return array
     */
    public function calculateColumns(Invoice $invoice, float $amount): array
    {
        $tax_sales = $this->taxDataFloat($invoice, 'taxSales');
        $state_rate = $this->taxDataFloat($invoice, 'stateSalesTax');
        $county_rate = $this->taxDataFloat($invoice, 'countySalesTax');
        $city_rate = $this->taxDataFloat($invoice, 'citySalesTax');
        $district_rate = $this->taxDataFloat($invoice, 'districtSalesTax');

        if ($tax_sales == 0.0) {
            return [
                $this->state($invoice),
                $state_rate ?: '',
                '',
                $this->county($invoice),
                $county_rate ?: '',
                '',
                $this->city($invoice),
                $city_rate ?: '',
                '',
                $district_rate ?: '',
                '',
            ];
        }

        return [
            $this->state($invoice),
            $state_rate ?: '',
            round(($state_rate / $tax_sales) * $amount, 2),
            $this->county($invoice),
            $county_rate ?: '',
            round(($county_rate / $tax_sales) * $amount, 2),
            $this->city($invoice),
            $city_rate ?: '',
            round(($city_rate / $tax_sales) * $amount, 2),
            $district_rate ?: '',
            round(($district_rate / $tax_sales) * $amount, 2),
        ];
    }

    public function reportingBucket(Invoice $invoice, TaxDetail $tax_detail): string
    {
        return implode(' | ', array_filter([
            'US',
            $this->state($invoice),
            $this->county($invoice),
            $this->city($invoice),
            $this->districtCodeSummary($invoice),
            $this->postalCode($invoice, $tax_detail),
            $this->taxLabel($tax_detail),
            $tax_detail->classification ?: ctrans('texts.unknown'),
        ], fn (string $part): bool => $part !== ''));
    }

    public function jurisdictionSource(Invoice $invoice, TaxDetail $tax_detail): string
    {
        if ($this->hasTaxDataJurisdiction($invoice)) {
            return ctrans('texts.tax_data_source');
        }

        if ($this->hasShippingJurisdiction($invoice)) {
            return ctrans('texts.client_shipping_source');
        }

        if ($this->hasBillingJurisdiction($invoice)) {
            return ctrans('texts.client_billing_source');
        }

        if (trim($tax_detail->postal_code) !== '') {
            return ctrans('texts.tax_detail_source');
        }

        return ctrans('texts.unknown_source');
    }

    public static function supports(string $country_iso): bool
    {
        return $country_iso === 'US';
    }

    private function state(Invoice $invoice): string
    {
        return $this->normalize($this->taxDataString($invoice, 'geoState')
            ?: $invoice->client->shipping_state
            ?: $invoice->client->state
            ?: '');
    }

    private function county(Invoice $invoice): string
    {
        return $this->normalize($this->taxDataString($invoice, 'geoCounty'));
    }

    private function city(Invoice $invoice): string
    {
        return $this->normalize($this->taxDataString($invoice, 'geoCity')
            ?: $invoice->client->shipping_city
            ?: $invoice->client->city
            ?: '');
    }

    private function postalCode(Invoice $invoice, TaxDetail $tax_detail): string
    {
        return trim((string) ($this->taxDataString($invoice, 'geoPostalCode')
            ?: $tax_detail->postal_code
            ?: $invoice->client->shipping_postal_code
            ?: $invoice->client->postal_code
            ?: ''));
    }

    private function districtCodeSummary(Invoice $invoice): string
    {
        $codes = [];

        for ($i = 1; $i <= 5; $i++) {
            $code = $this->taxDataString($invoice, "district{$i}Code");

            if ($code !== '') {
                $codes[] = $code;
            }
        }

        $codes = array_values(array_unique($codes));

        if (empty($codes)) {
            return '';
        }

        return 'Districts ' . implode('/', $codes);
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

    private function taxDataString(Invoice $invoice, string $key): string
    {
        return trim((string) data_get($invoice->tax_data, $key, ''));
    }

    private function taxDataFloat(Invoice $invoice, string $key): float
    {
        return (float) data_get($invoice->tax_data, $key, 0);
    }

    private function hasTaxDataJurisdiction(Invoice $invoice): bool
    {
        return $this->taxDataString($invoice, 'geoState') !== ''
            || $this->taxDataString($invoice, 'geoCounty') !== ''
            || $this->taxDataString($invoice, 'geoCity') !== ''
            || $this->taxDataString($invoice, 'geoPostalCode') !== '';
    }

    private function hasShippingJurisdiction(Invoice $invoice): bool
    {
        return trim((string) ($invoice->client->shipping_state ?? '')) !== ''
            || trim((string) ($invoice->client->shipping_city ?? '')) !== ''
            || trim((string) ($invoice->client->shipping_postal_code ?? '')) !== '';
    }

    private function hasBillingJurisdiction(Invoice $invoice): bool
    {
        return trim((string) ($invoice->client->state ?? '')) !== ''
            || trim((string) ($invoice->client->city ?? '')) !== ''
            || trim((string) ($invoice->client->postal_code ?? '')) !== '';
    }

    private function normalize(?string $value): string
    {
        return trim((string) $value);
    }
}
