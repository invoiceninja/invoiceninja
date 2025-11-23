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
 * Tax summary with totals for different tax states
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
    public float $taxable_amount;
    public float $tax_amount;
    public string $status;

    public function __construct(array $attributes = [])
    {
        $this->taxable_amount = $attributes['taxable_amount'] ?? 0.0;
        // Support both old and new property names for backwards compatibility during migration
        $this->tax_amount = $attributes['tax_amount'] ?? $attributes['tax_adjustment'] ?? $attributes['total_taxes'] ?? 0.0;
        $this->status = $attributes['status'] ?? 'updated';
    }

    public function toArray(): array
    {
        return [
            'taxable_amount' => $this->taxable_amount,
            'tax_amount' => $this->tax_amount,
            'status' => $this->status,
        ];
    }
}
