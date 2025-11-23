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
use App\Models\TransactionEvent;

/**
 * Builds invoice-level report rows with type-safe access
 */
class InvoiceReportRow
{
    private array $row_data = [];

    public function __construct(
        private Invoice $invoice,
        private TransactionEvent $event,
        private TaxSummary $tax_summary,
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
            ctrans('texts.invoice_total'),
            ctrans('texts.paid'),
            ctrans('texts.tax_amount'),
            ctrans('texts.taxable_amount'),
            ctrans('texts.notes'),
        ];

        if ($regional_calculator) {
            return array_merge($base_headers, $regional_calculator->getHeaders());
        }

        return $base_headers;
    }

    /**
     * Build row for 'updated' status - full invoice amount
     */
    public function buildUpdatedRow(): array
    {
        $this->row_data = [
            $this->invoice->number,
            $this->invoice->date,
            $this->event->invoice_amount,
            $this->event->invoice_paid_to_date,
            $this->tax_summary->tax_amount,
            $this->tax_summary->taxable_amount,
            $this->tax_summary->status->label(),
        ];

        $this->appendRegionalColumns($this->tax_summary->tax_amount);

        return $this->row_data;
    }

    /**
     * Build row for 'delta' status - differential change
     */
    public function buildDeltaRow(): array
    {
        $this->row_data = [
            $this->invoice->number,
            $this->invoice->date,
            $this->event->invoice_amount,
            $this->event->metadata->tax_report->payment_history?->sum('amount') ?? 0,
            $this->tax_summary->tax_amount,
            $this->tax_summary->taxable_amount,
            $this->tax_summary->status->label(),
        ];

        $this->appendRegionalColumns($this->tax_summary->tax_amount);

        return $this->row_data;
    }

    /**
     * Build row for 'adjustment' status - payment refund/deletion
     */
    public function buildAdjustmentRow(): array
    {
        $this->row_data = [
            $this->invoice->number,
            $this->invoice->date,
            $this->event->invoice_amount,
            $this->event->invoice_paid_to_date,
            $this->tax_summary->tax_amount,
            $this->tax_summary->taxable_amount, // Negative adjustment amount
            $this->tax_summary->status->label(),
        ];

        $this->appendRegionalColumns($this->tax_summary->tax_amount);

        return $this->row_data;
    }

    /**
     * Build row for 'cancelled' status
     */
    public function buildCancelledRow(): array
    {
        $this->row_data = [
            $this->invoice->number,
            $this->invoice->date,
            $this->event->invoice_paid_to_date,
            $this->event->metadata->tax_report->payment_history?->sum('amount') ?? 0,
            $this->tax_summary->tax_amount,
            $this->tax_summary->taxable_amount,
            $this->tax_summary->status->label(),
        ];

        $this->appendRegionalColumns($this->tax_summary->tax_amount);

        return $this->row_data;
    }

    /**
     * Build row for 'deleted' status - negative values
     */
    public function buildDeletedRow(): array
    {
        $this->row_data = [
            $this->invoice->number,
            $this->invoice->date,
            $this->invoice->amount * -1,
            ($this->event->metadata->tax_report->payment_history?->sum('amount') ?? 0) * -1,
            $this->tax_summary->tax_amount,
            $this->tax_summary->taxable_amount,
            $this->tax_summary->status->label(),
        ];

        $this->appendRegionalColumns($this->tax_summary->tax_amount);

        return $this->row_data;
    }

    /**
     * Build the appropriate row based on status
     */
    public function build(): array
    {
        return match($this->tax_summary->status) {
            TaxReportStatus::UPDATED => $this->buildUpdatedRow(),
            TaxReportStatus::DELTA => $this->buildDeltaRow(),
            TaxReportStatus::ADJUSTMENT => $this->buildAdjustmentRow(),
            TaxReportStatus::CANCELLED => $this->buildCancelledRow(),
            TaxReportStatus::DELETED => $this->buildDeletedRow(),
            TaxReportStatus::RESTORED => $this->buildUpdatedRow(), // Treat restored as updated
            TaxReportStatus::REVERSED => $this->buildDeltaRow(),
        };
    }

    /**
     * Append regional tax columns
     */
    private function appendRegionalColumns(float $tax_amount): void
    {
        if ($this->regional_calculator) {
            $regional_columns = $this->regional_calculator->calculateColumns($this->invoice, $tax_amount);
            $this->row_data = array_merge($this->row_data, $regional_columns);
        }
    }
}
