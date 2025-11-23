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

namespace App\DataMapper\TaxReport;

/**
 * Individual tax detail object with status tracking
 */
class TaxDetail
{
    public string $tax_name; // e.g., Sales Tax
    public float $tax_rate = 0; //21%
    public string $nexus; // Tax jurisdiction nexus (e.g. "CA", "NY", "FL")
    public string $country_nexus; // Country nexus (e.g. "US", "UK", "CA")
    public float $taxable_amount; // net amount exclusive of taxes
    public float $tax_amount; // total tax amount
    public string $tax_status; // "collected", "pending", "refundable", "partially_paid", "adjustment"
    
    // Adjustment-specific fields (used when tax_status is "adjustment")
    public ?string $postal_code; // "invoice_cancelled", "tax_rate_change", "exemption_applied", "correction"
    public float $line_total;
    public float $total_tax;
    
  
    public function __construct(array $attributes = [])
    {
        $this->tax_name = $attributes['tax_name'] ?? '';
        $this->tax_rate = $attributes['tax_rate'] ?? 0;
        $this->nexus = $attributes['nexus'] ?? '';
        $this->country_nexus = $attributes['country_nexus'] ?? '';
        $this->taxable_amount = $attributes['taxable_amount'] ?? 0.0;
        $this->tax_amount = $attributes['tax_amount'] ?? 0.0;
        $this->tax_status = $attributes['tax_status'] ?? 'pending';
        // Adjustment fields
        $this->postal_code = $attributes['postal_code'] ?? null;
        
        $this->line_total = $attributes['line_total'] ?? 0.0;
        $this->total_tax = $attributes['total_tax'] ?? 0.0;
    }

    public function toArray(): array
    {
        $data = [
            'tax_name' => $this->tax_name,
            'tax_rate' => $this->tax_rate,
            'nexus' => $this->nexus,
            'country_nexus' => $this->country_nexus,
            'taxable_amount' => $this->taxable_amount,
            'tax_amount' => $this->tax_amount,
            'tax_status' => $this->tax_status,
            'postal_code' => $this->postal_code,
            'line_total' => $this->line_total,
            'total_tax' => $this->total_tax,
        ];

        return $data;
    }
}
