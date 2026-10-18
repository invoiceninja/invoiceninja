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

use RuntimeException;

final class TxnTaxDetailBuilder
{
    public function __construct(private InvoiceTaxCodeResolver $resolver)
    {
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $tax_map
     * @param  array<int, mixed>  $tax_rate_map
     * @return array<string, mixed>|null
     */
    public function build(iterable $tax_map, float $total_taxes, array $tax_rate_map): ?array
    {
        $tax_lines = [];
        $calculated_total_tax = 0;

        foreach ($tax_map as $tax) {
            $tax_name = (string) $tax['name'];
            $tax_rate = (float) $tax['tax_rate'];

            $tax_rate_id = $this->resolver->findTaxRateIdByRateAndName(
                $tax_rate_map,
                $tax_rate,
                $tax_name
            );

            if (!$tax_rate_id) {
                throw new RuntimeException("QuickBooks TaxRate unavailable: {$tax_name} ({$tax_rate}%)");
            }

            $tax_lines[] = [
                'Amount' => round($tax['total'], 2),
                'DetailType' => 'TaxLineDetail',
                'TaxLineDetail' => [
                    'TaxRateRef' => [
                        'value' => $tax_rate_id,
                    ],
                    'PercentBased' => true,
                    'TaxPercent' => $tax_rate,
                    'NetAmountTaxable' => round($tax['base_amount'], 2),
                ],
            ];

            $calculated_total_tax += round($tax['total'], 2);
        }

        if (empty($tax_lines)) {
            return null;
        }

        $final_total_tax = $total_taxes > 0 ? round($total_taxes, 2) : round($calculated_total_tax, 2);

        return [
            'TotalTax' => $final_total_tax,
            'TaxLine' => $tax_lines,
        ];
    }
}
