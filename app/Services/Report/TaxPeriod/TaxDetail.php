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

/**
 * Data Transfer Object for tax detail line items
 */
class TaxDetail
{
    public function __construct(
        public string $tax_name,
        public float $tax_rate,
        public float $taxable_amount,
        public float $tax_amount,
        public float $line_total = 0,
        public float $total_tax = 0,
        public string $postal_code = '',
    ) {}

    /**
     * Create from transaction event metadata
     */
    public static function fromMetadata(object $metadata): self
    {
        return new self(
            tax_name: $metadata->tax_name,
            tax_rate: $metadata->tax_rate,
            taxable_amount: $metadata->taxable_amount ?? 0,
            tax_amount: $metadata->tax_amount ?? 0,
            line_total: $metadata->line_total ?? 0,
            total_tax: $metadata->total_tax ?? 0,
            postal_code: $metadata->postal_code ?? '',
        );
    }

    /**
     * Calculate tax amount paid based on invoice payment ratio
     *
     * @param float $payment_ratio The ratio of invoice paid (paid_to_date / amount)
     * @return float Tax amount that has been paid
     */
    public function calculateTaxPaid(float $payment_ratio): float
    {
        return round($this->tax_amount * $payment_ratio, 2);
    }

    /**
     * Calculate tax amount remaining (unpaid)
     *
     * @param float $payment_ratio The ratio of invoice paid
     * @return float Tax amount still outstanding
     */
    public function calculateTaxRemaining(float $payment_ratio): float
    {
        return round($this->tax_amount * (1 - $payment_ratio), 2);
    }

    /**
     * Calculate taxable amount paid (for cash accounting)
     *
     * @param float $payment_ratio The ratio of invoice paid
     * @return float Taxable amount that corresponds to paid portion
     */
    public function calculateTaxableAmountPaid(float $payment_ratio): float
    {
        return round($this->taxable_amount * $payment_ratio, 2);
    }

    /**
     * Calculate taxable amount remaining
     *
     * @param float $payment_ratio The ratio of invoice paid
     * @return float Taxable amount still outstanding
     */
    public function calculateTaxableAmountRemaining(float $payment_ratio): float
    {
        return round($this->taxable_amount * (1 - $payment_ratio), 2);
    }


    /**
     * Get effective tax rate as percentage string (e.g., "10%")
     */
    public function getTaxRateFormatted(): string
    {
        return number_format($this->tax_rate, 2) . '%';
    }

    /**
     * Get effective tax rate as decimal (for calculations, e.g., 0.10)
     */
    public function getTaxRateDecimal(): float
    {
        return $this->tax_rate / 100;
    }

    /**
     * Verify tax calculation integrity (tax_amount ≈ taxable_amount × tax_rate)
     *
     * @param float $tolerance Acceptable variance in cents
     * @return bool True if calculation is within tolerance
     */
    public function verifyTaxCalculation(float $tolerance = 0.02): bool
    {
        $expected_tax = round($this->taxable_amount * $this->getTaxRateDecimal(), 2);
        return abs($this->tax_amount - $expected_tax) <= $tolerance;
    }

    /**
     * Convert to array for spreadsheet export
     */
    public function toArray(): array
    {
        return [
            'tax_name' => $this->tax_name,
            'tax_rate' => $this->tax_rate,
            'taxable_amount' => $this->taxable_amount,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'total_tax' => $this->total_tax,
            'postal_code' => $this->postal_code,
        ];
    }
}
