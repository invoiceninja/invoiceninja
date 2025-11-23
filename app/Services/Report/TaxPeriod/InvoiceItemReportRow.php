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
 * Builds invoice item-level (tax detail) report rows
 */
class InvoiceItemReportRow
{
    public function __construct(
        private Invoice $invoice,
        private TaxDetail $tax_detail,
        private TaxReportStatus $status,
        private ?RegionalTaxCalculator $regional_calculator = null,
    ) {
    }

    /**
     * Get column headers
     */
    public static function getHeaders(?RegionalTaxCalculator $regional_calculator = null): array
    {
        $base_headers = [
            ctrans('texts.invoice_number'),
            ctrans('texts.invoice_date'),
            ctrans('texts.tax_name'),
            ctrans('texts.tax_rate'),
            ctrans('texts.tax_amount'),
            ctrans('texts.taxable_amount'),
            ctrans('texts.status'),
            ctrans('texts.postal_code'),
        ];

        if ($regional_calculator) {
            return array_merge($base_headers, $regional_calculator->getHeaders());
        }

        return $base_headers;
    }

    /**
     * Build row for normal status (updated, cancelled, deleted)
     */
    public function build(): array
    {
        $row = [
            $this->invoice->number,
            $this->invoice->date,
            $this->tax_detail->tax_name,
            $this->tax_detail->tax_rate,
            $this->tax_detail->tax_amount,
            $this->tax_detail->taxable_amount,
            $this->status->label(),
            $this->tax_detail->postal_code,
        ];

        return $this->appendRegionalColumns($row, $this->tax_detail->tax_amount);
    }

    /**
     * Build row for delta/adjustment status - show adjustments
     */
    public function buildAdjustmentRow(): array
    {
        $row = [
            $this->invoice->number,
            $this->invoice->date,
            $this->tax_detail->tax_name,
            $this->tax_detail->tax_rate,
            $this->tax_detail->tax_amount,
            $this->tax_detail->taxable_amount,
            $this->status->label(),
            $this->tax_detail->postal_code,
        ];

        return $this->appendRegionalColumns($row, $this->tax_detail->tax_amount);
    }

    /**
     * Build the appropriate row based on status
     */
    public function buildForStatus(): array
    {
        return match($this->status) {
            TaxReportStatus::DELTA, TaxReportStatus::ADJUSTMENT => $this->buildAdjustmentRow(),
            default => $this->build(),
        };
    }

    /**
     * Append regional tax columns
     */
    private function appendRegionalColumns(array $row, float $tax_amount): array
    {
        if ($this->regional_calculator) {
            $regional_columns = $this->regional_calculator->calculateColumns($this->invoice, $tax_amount);
            return array_merge($row, $regional_columns);
        }

        return $row;
    }
}
