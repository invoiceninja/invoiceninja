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

namespace App\Listeners\Invoice;

use App\Utils\BcMath;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use Illuminate\Support\Collection;
use App\DataMapper\TransactionEventMetadata;
use App\Services\Report\TaxPeriod\TaxClassificationCalculator;
use App\Services\Report\TaxPeriod\SalesBreakdownCalculator;

/**
 * Handles entries for invoices.
 * Used for end of month aggregation of accrual accounting.
 */
class InvoiceTransactionEventEntry
{
    private Collection $payments;

    private float $paid_ratio;

    private string $entry_type = 'updated';

    /**
     * Handle the event.
     *
     * @param  Invoice  $invoice
     * @return void
     */
    public function run(?Invoice $invoice, ?string $force_period = null)
    {

        if (!$invoice) {
            return;
        }

        $this->setPaidRatio($invoice);

        //Long running tasks may spill over into the next day therefore month!
        $period = $force_period ?? now()->endOfMonth()->subHours(5)->format('Y-m-d');

        $event = $invoice->transaction_events()
                        ->where('event_id', TransactionEvent::INVOICE_UPDATED)
                        ->orderBy('timestamp', 'desc')
                        ->first();

        if ($event) {


            $this->entry_type = 'delta';
            // nlog($event->period->format('Y-m-d') . " ". $period);

            // if($force_period && $event->period->format('Y-m-d') == $force_period){
            //     nlog("already have an event!!");
            //     return;
            // }
            // else
            if ($invoice->is_deleted && $event->metadata->tax_report->tax_summary->status == 'deleted') {
                // Invoice was previously deleted, and is still deleted... return early!!
                return;
            } elseif (in_array($invoice->status_id, [Invoice::STATUS_CANCELLED]) && $event->metadata->tax_report->tax_summary->status == 'cancelled') {
                // Invoice was previously cancelled, and is still cancelled... return early!!
                return;
            } elseif (in_array($invoice->status_id, [Invoice::STATUS_REVERSED]) && $event->metadata->tax_report->tax_summary->status == 'reversed') {
                // Invoice was previously cancelled, and is still cancelled... return early!!
                return;
            } elseif (!$invoice->is_deleted && $event->metadata->tax_report->tax_summary->status == 'deleted') {
                //restored invoice must be reported!!!! _do not return early!!
                $this->entry_type = 'restored';
            } elseif (in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED])) {
                // Need to ensure first time cancellations are reported.
                // return; // Only return if BOTH amount AND status unchanged - for handling cancellations.

                // return;
            } elseif ($invoice->is_deleted) {

            }
            /** If the invoice hasn't changed its state... return early!! */ 
            elseif (BcMath::comp($invoice->amount, $event->invoice_amount) == 0 || $event->period->format('Y-m-d') == $period) {
                // nlog("event period => {$period} => " . $event->period->format('Y-m-d'));
                // nlog("invoice amount => {$invoice->amount} => " . $event->invoice_amount);
                // nlog("apparently no change in amount or period");
                return;
            }

        } elseif ($invoice->is_deleted) {
            // elseif($invoice->is_deleted && \Carbon\Carbon::parse($invoice->date)->lte(\Carbon\Carbon::parse($period))){
            //If the invoice was created and deleted in the same period, we don't need to report it!!!
            // return;

        }

        // nlog("invoice amount => {$invoice->amount}");
        $this->payments = $invoice->payments->map(function ($payment) use ($invoice) {

            /** @var \App\Models\Paymentable $pivot */
            $pivot = $payment->invoices()->where('paymentable_id', $invoice->id)->first()?->pivot;

            if (!$pivot) {
                return null;
            }

            return [
                'number' => $payment->number,
                'amount' => $pivot->amount,
                'refunded' => $pivot->refunded,
                'date' => $pivot->created_at->format('Y-m-d'),
            ];
        })->filter();

        TransactionEvent::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'client_balance' => $invoice->client->balance,
            'client_paid_to_date' => $invoice->client->paid_to_date,
            'client_credit_balance' => $invoice->client->credit_balance,
            'invoice_balance' => $invoice->balance ?? 0,
            'invoice_amount' => $invoice->amount ?? 0,
            'invoice_partial' => $invoice->partial ?? 0,
            'invoice_paid_to_date' => $invoice->paid_to_date ?? 0,
            'invoice_status' => $invoice->is_deleted ? 7 : $invoice->status_id,
            'event_id' => TransactionEvent::INVOICE_UPDATED,
            'timestamp' => now()->timestamp,
            'metadata' => $this->getMetadata($invoice),
            'period' => $period,
        ]);
    }

    private function setPaidRatio(Invoice $invoice): self
    {
        if ($invoice->amount == 0) {
            $this->paid_ratio = 0;
            return $this;
        }

        $this->paid_ratio = $invoice->paid_to_date / $invoice->amount;

        return $this;
    }

    /**
     * calculateDeltaMetaData
     *
     * Calculates the differential between this period and the previous period.
     *
     * @param  mixed $invoice
     *
     */
    private function calculateDeltaMetaData($invoice)
    {
        $this->paid_ratio = 1;

        $calc = $invoice->calc();

        $details = [];

        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());
        $previous_transaction_event = TransactionEvent::where('event_id', TransactionEvent::INVOICE_UPDATED)
                                            ->where('invoice_id', $invoice->id)
                                            ->orderBy('timestamp', 'desc')
                                            ->first();


        $previous_tax_details = $previous_transaction_event->metadata->tax_report->tax_details;
        $previous_sales_breakdown = $previous_transaction_event->metadata->tax_report->sales_breakdown ?? [];
        $sales_breakdown = !empty($previous_sales_breakdown)
            ? SalesBreakdownCalculator::calculateDelta($invoice, $previous_sales_breakdown)
            : null;

        $postal_code = $invoice->client->postal_code;

        foreach ($taxes as $tax) {
            $previousLine = collect($previous_tax_details)->where('tax_name', $tax['name'])->first() ?? null;

            $tax_detail = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'line_total' => ($tax['base_amount'] ?? $calc->getNetSubtotal()),
                'total_tax' => $tax['total'],
                'taxable_amount' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) - ($previousLine->line_total ?? 0),
                'tax_amount' => $tax['total'] - ($previousLine->total_tax ?? 0),
                'postal_code' => $postal_code,
            ];

            $details[] = $tax_detail;
        }

        $this->setPaidRatio($invoice);

        // Calculate cumulative previous tax by summing all previous event tax amounts
        $all_events = TransactionEvent::where('event_id', TransactionEvent::INVOICE_UPDATED)
                                        ->where('invoice_id', $invoice->id)
                                        ->orderBy('timestamp', 'asc')
                                        ->get();

        $cumulative_tax = 0;
        $cumulative_taxable = 0;
        foreach ($all_events as $event) {
            $cumulative_tax += $event->metadata->tax_report->tax_summary->tax_amount ?? 0;
            $cumulative_taxable += $event->metadata->tax_report->tax_summary->taxable_amount ?? 0;
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'tax_details_by_classification' => $this->buildDeltaByClassification($invoice),
                'sales_breakdown' => $sales_breakdown,
                'payment_history' => $this->payments->toArray() ?? [], //@phpstan-ignore-line
                'tax_summary' => [
                    'taxable_amount' => $calc->getNetSubtotal() - $cumulative_taxable,
                    'tax_amount' => round($calc->getTotalTaxes() - $cumulative_tax, 2),
                    'status' => 'delta',
                ],
            ],
        ]);

    }
    
    /**
     * getReversedMetaData
     *
     * @param  mixed $invoice
     * @return TransactionEventMetadata
     */
    private function getReversedMetaData($invoice)
    {
        $calc = $invoice->calc();

        $details = [];

        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

        $postal_code = $invoice->client->postal_code;

        foreach ($taxes as $tax) {
            $tax_detail = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $this->paid_ratio * -1,
                'tax_amount' => ($tax['total'] * $this->paid_ratio * -1),
                'line_total' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $this->paid_ratio * -1,
                'total_tax' => $tax['total'] * $this->paid_ratio * -1,
                'postal_code' => $postal_code,

            ];
            $details[] = $tax_detail;
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'tax_details_by_classification' => TaxClassificationCalculator::calculate($invoice, $this->paid_ratio * -1, $details),
                'sales_breakdown' => SalesBreakdownCalculator::calculate($invoice, $this->paid_ratio * -1),
                'payment_history' => $this->payments->toArray() ?? [], //@phpstan-ignore-line
                'tax_summary' => [
                    'taxable_amount' => $calc->getNetSubtotal() * $this->paid_ratio * -1,
                    'tax_amount' => $calc->getTotalTaxes() * $this->paid_ratio * -1,
                    'status' => 'reversed',
                ],
            ],
        ]);

    }

    /**
     * Existing tax details are not deleted, but pending taxes are set to 0
     *
     * @param  mixed $invoice
     */
    private function getCancelledMetaData($invoice)
    {

        $calc = $invoice->calc();

        $details = [];

        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

        $postal_code = $invoice->client->postal_code;


        foreach ($taxes as $tax) {
            $tax_detail = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $this->paid_ratio,
                'tax_amount' => ($tax['total'] * $this->paid_ratio),
                'line_total' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $this->paid_ratio,
                'total_tax' => $tax['total'] * $this->paid_ratio,
                'postal_code' => $postal_code,
            ];
            $details[] = $tax_detail;
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'tax_details_by_classification' => TaxClassificationCalculator::calculate($invoice, $this->paid_ratio, $details),
                'sales_breakdown' => SalesBreakdownCalculator::calculate($invoice, $this->paid_ratio),
                'payment_history' => $this->payments->toArray() ?? [], //@phpstan-ignore-line
                'tax_summary' => [
                    'taxable_amount' => $calc->getNetSubtotal() * $this->paid_ratio,
                    'tax_amount' => $calc->getTotalTaxes() * $this->paid_ratio,
                    'status' => 'cancelled',
                ],
            ],
        ]);

    }

    /**
     * Set all tax details to 0
     *
     * @param  mixed $invoice
     */
    private function getDeletedMetaData($invoice)
    {

        $calc = $invoice->calc();

        $details = [];

        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());
        $postal_code = $invoice->client->postal_code;

        foreach ($taxes as $tax) {
            $tax_detail = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * -1,
                'tax_amount' => $tax['total'] * -1,
                'line_total' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * -1,
                'total_tax' => $tax['total'] * -1,
                'postal_code' => $postal_code,
            ];
            $details[] = $tax_detail;
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'tax_details_by_classification' => TaxClassificationCalculator::calculate($invoice, -1, $details),
                'sales_breakdown' => SalesBreakdownCalculator::calculate($invoice, -1),
                'payment_history' => $this->payments->toArray(),
                'tax_summary' => [
                    'taxable_amount' => $calc->getNetSubtotal() * -1,
                    'tax_amount' => $calc->getTotalTaxes() * -1,
                    'status' => 'deleted',
                ],
            ],
        ]);

    }

    /**
     * getMetadata
     *
     * @param  mixed $invoice
     * @return TransactionEventMetadata
     */
    private function getMetadata($invoice): TransactionEventMetadata
    {

        if ($invoice->status_id == Invoice::STATUS_CANCELLED) {
            return $this->getCancelledMetaData($invoice);
        } elseif ($invoice->is_deleted) {
            return $this->getDeletedMetaData($invoice);
        } elseif ($invoice->status_id == Invoice::STATUS_REVERSED) {
            return $this->getReversedMetaData($invoice);
        } elseif ($this->entry_type == 'delta') {
            return $this->calculateDeltaMetaData($invoice);
        }

        $calc = $invoice->calc();

        $details = [];

        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());
        $postal_code = $invoice->client->postal_code;

        foreach ($taxes as $tax) {
            $tax_detail = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => $tax['base_amount'] ?? $calc->getNetSubtotal(),
                'tax_amount' => $tax['total'],
                'line_total' => $tax['base_amount'] ?? $calc->getNetSubtotal(),
                'total_tax' => $tax['total'],
                'postal_code' => $postal_code,
            ];
            $details[] = $tax_detail;
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'tax_details_by_classification' => TaxClassificationCalculator::calculate($invoice, 1.0, $details),
                'sales_breakdown' => SalesBreakdownCalculator::calculate($invoice, 1.0),
                'payment_history' => $this->payments->toArray(),
                'tax_summary' => [
                    'taxable_amount' => $calc->getNetSubtotal(),
                    'tax_amount' => $calc->getTotalTaxes(),
                    'status' => 'updated',
                ],
            ],
        ]);

    }

    /**
     * Delta semantics mirror the existing tax_details flow:
     *  - taxable_amount / tax_amount are the period delta
     *  - line_total / total_tax are the full current snapshot (so the next
     *    delta event can subtract from them)
     *
     * The previous event's `line_total` and `total_tax` carry its full
     * cumulative current per classification.
     */
    private function buildDeltaByClassification(Invoice $invoice): array
    {
        $current = TaxClassificationCalculator::calculate($invoice, 1.0, $this->fullCurrentAggregate($invoice));

        $previous_event = TransactionEvent::where('event_id', TransactionEvent::INVOICE_UPDATED)
            ->where('invoice_id', $invoice->id)
            ->orderBy('timestamp', 'desc')
            ->first();

        $previous_index = [];
        if ($previous_event) {
            $previous = $previous_event->metadata->tax_report->tax_details_by_classification ?? [];
            // if ($previous instanceof \Illuminate\Support\Collection) {
            //     $previous = $previous->toArray();
            // }

            foreach ($previous as $row) {
                $row = is_array($row) ? $row : (array) $row;
                $key = ($row['tax_name'] ?? '') . '|' . ($row['tax_rate'] ?? 0) . '|' . ($row['classification'] ?? '');
                $previous_index[$key] = $row;
            }
        }

        $delta = [];
        foreach ($current as $row) {
            $key = $row['tax_name'] . '|' . $row['tax_rate'] . '|' . $row['classification'];
            $prev = $previous_index[$key] ?? null;

            $prev_full_taxable = (float) ($prev['line_total'] ?? 0);
            $prev_full_tax = (float) ($prev['total_tax'] ?? 0);

            $delta[] = [
                'tax_name' => $row['tax_name'],
                'tax_rate' => $row['tax_rate'],
                'classification' => $row['classification'],
                'taxable_amount' => round($row['taxable_amount'] - $prev_full_taxable, 2),
                'tax_amount' => round($row['tax_amount'] - $prev_full_tax, 2),
                'line_total' => $row['taxable_amount'],
                'total_tax' => $row['tax_amount'],
                'postal_code' => $row['postal_code'],
            ];
        }

        return $delta;
    }

    /**
     * Build the un-multiplied aggregate tax_details (current state) so the
     * classification calculator can reconcile against authoritative totals
     * before delta subtraction.
     */
    private function fullCurrentAggregate(Invoice $invoice): array
    {
        $calc = $invoice->calc();
        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());
        $postal_code = $invoice->client->postal_code;

        $aggregate = [];
        foreach ($taxes as $tax) {
            $aggregate[] = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => $tax['base_amount'] ?? $calc->getNetSubtotal(),
                'tax_amount' => $tax['total'],
                'postal_code' => $postal_code,
            ];
        }
        return $aggregate;
    }

}
