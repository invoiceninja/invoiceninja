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
 * Data Transfer Object for tax summary information
 *
 * Represents the taxable amount and tax amount for an invoice in a specific period.
 * The meaning of these values depends on the status:
 *
 * - 'updated': Full invoice tax liability (accrual) or paid tax (cash)
 * - 'delta': Differential tax change from invoice updates
 * - 'adjustment': Tax change from payment refunds/deletions
 * - 'cancelled': Proportional tax on refunded/cancelled amount
 * - 'deleted': Full tax reversal
 * - 'reversed': Full tax reversal of credit note
 */
class TaxSummary
{
    public function __construct(
        public float $taxable_amount,
        public float $tax_amount,
        public TaxReportStatus $status,
    ) {
    }

    /**
     * Create from transaction event metadata
     */
    public static function fromMetadata($metadata): self
    {
        // Handle both object and array access
        $taxable_amount = is_array($metadata) ? ($metadata['taxable_amount'] ?? 0) : ($metadata->taxable_amount ?? 0);
        $tax_amount = is_array($metadata) ? ($metadata['tax_amount'] ?? $metadata['tax_adjustment'] ?? 0) : ($metadata->tax_amount ?? $metadata->tax_adjustment ?? 0);
        $status = is_array($metadata) ? ($metadata['status'] ?? 'updated') : ($metadata->status ?? 'updated');

        return new self(
            taxable_amount: $taxable_amount,
            tax_amount: $tax_amount,
            status: TaxReportStatus::from($status),
        );
    }

    /**
     * Get the payment ratio for this invoice
     *
     * @param float $invoice_amount Total invoice amount
     * @param float $invoice_paid_to_date Amount paid on invoice
     * @return float Ratio of amount paid (0 to 1)
     */
    public function getPaymentRatio(float $invoice_amount, float $invoice_paid_to_date): float
    {
        return $invoice_amount > 0 ? $invoice_paid_to_date / $invoice_amount : 0;
    }

    /**
     * Convert to array for spreadsheet export
     */
    public function toArray(): array
    {
        return [
            'taxable_amount' => $this->taxable_amount,
            'tax_amount' => $this->tax_amount,
            'status' => $this->status->value,
        ];
    }
}
