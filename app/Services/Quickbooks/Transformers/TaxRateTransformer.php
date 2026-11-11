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

namespace App\Services\Quickbooks\Transformers;

/**
 */
class TaxRateTransformer
{
    /**
     * Transform a single QuickBooks Tax Rate (IPPTaxRate or array) to a simple map.
     *
     *
     * @param  object|array  $tax_rate  Raw Tax Rate from QuickBooks API (e.g. IPPTaxRate)
     * @return array{id: string, name: string, rate: float}
     */
    public function transform(object|array $tax_rate): array
    {
        // Extract QuickBooks TaxRate ID
        $qb_id = (string) (data_get($tax_rate, 'Id.value') ?? data_get($tax_rate, 'Id') ?? '');

        // Extract TaxRate name
        $name = (string) (data_get($tax_rate, 'Name') ?? '');

        $rate = 0.0;

        // First, try to get RateValue directly from the TaxRate object
        $rate_value = data_get($tax_rate, 'RateValue');
        if ($rate_value !== null && $rate_value !== '') {
            $rate = (float) $rate_value;
        } else {
            // If not found directly, try TaxRateDetails array
            $tax_rate_details = data_get($tax_rate, 'TaxRateDetails');

            if (is_array($tax_rate_details) && !empty($tax_rate_details)) {
                // Get the first TaxRateDetail's RateValue
                $rate_value = data_get($tax_rate_details[0], 'RateValue');
                if ($rate_value !== null && $rate_value !== '') {
                    $rate = (float) $rate_value;
                }
            } elseif (is_object($tax_rate_details)) {
                // Handle case where TaxRateDetails is an object, not an array
                $rate_value = data_get($tax_rate_details, 'RateValue');
                if ($rate_value !== null && $rate_value !== '') {
                    $rate = (float) $rate_value;
                }
            }
        }

        return [
            'id' => $qb_id,
            'name' => $name,
            'rate' => $rate,
        ];
    }

    /**
     * Transform an array of QuickBooks TaxRate objects.
     *
     * @param  array  $tax_rates  Array of IPPTaxRate (or array) from QuickBooks API
     * @return array<int, array{id: string, name: string, rate: float}>
     */
    public function transformMany(array $tax_rates): array
    {
        $result = [];
        foreach ($tax_rates as $tax_rate) {
            $mapped = $this->transform($tax_rate);
            if ($mapped['id'] !== '') {
                $result[] = $mapped;
            }
        }
        return $result;
    }
}
