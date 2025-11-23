<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
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
        $tax_data = $invoice->tax_data;

        // If no tax sales data, return empty columns
        if (!isset($tax_data->taxSales) || $tax_data->taxSales == 0) {
            return [
                $tax_data->geoState ?? '',
                $tax_data->stateSalesTax ?? '',
                '',
                $tax_data->geoCounty ?? '',
                $tax_data->countySalesTax ?? '',
                '',
                $tax_data->geoCity ?? '',
                $tax_data->citySalesTax ?? '',
                '',
                $tax_data->districtSalesTax ?? '',
                '',
            ];
        }

        // Calculate proportional allocation
        $total_tax_sales = $tax_data->taxSales;

        $state_tax_amount = round(($tax_data->stateSalesTax / $total_tax_sales) * $amount, 2);
        $county_tax_amount = round(($tax_data->countySalesTax / $total_tax_sales) * $amount, 2);
        $city_tax_amount = round(($tax_data->citySalesTax / $total_tax_sales) * $amount, 2);
        $district_tax_amount = round(($tax_data->districtSalesTax / $total_tax_sales) * $amount, 2);

        return [
            $tax_data->geoState ?? '',
            $tax_data->stateSalesTax ?? '',
            $state_tax_amount,
            $tax_data->geoCounty ?? '',
            $tax_data->countySalesTax ?? '',
            $county_tax_amount,
            $tax_data->geoCity ?? '',
            $tax_data->citySalesTax ?? '',
            $city_tax_amount,
            $tax_data->districtSalesTax ?? '',
            $district_tax_amount,
        ];
    }

    public static function supports(string $country_iso): bool
    {
        return $country_iso === 'US';
    }
}
