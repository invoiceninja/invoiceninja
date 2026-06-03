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

use App\Models\Invoice;
use App\Models\TransactionEvent;
use Illuminate\Support\Collection;
use App\DataMapper\TransactionEventMetadata;
use App\Services\Report\TaxPeriod\TaxClassificationCalculator;
use App\Services\Report\TaxPeriod\SalesBreakdownCalculator;

/**
 * Handles entries for vanilla payments on an invoice.
 * Used for end of month aggregation of cash payments.
 */
class InvoiceTransactionEventEntryCash
{
    private Collection $payments;

    private float $paid_ratio;

    /**
     * Handle the event.
     *
     */
    public function run(?Invoice $invoice, string $start_date, string $end_date): void
    {

        if (!$invoice || $invoice->transaction_events()
            ->where('event_id', TransactionEvent::PAYMENT_CASH)
            ->where('period', $end_date)
            ->exists()) {
            return;
        }

        $this->payments = $invoice->payments->map(function ($payment) use ($invoice, $start_date, $end_date) {
            
            /** @var mixed $pivot */
            $pivot = $payment->invoices()->where('paymentable_id', $invoice->id)->first()?->pivot;

            if (!$pivot) {
                return null;
            }

            $date = $pivot->created_at->format('Y-m-d');

            if (!\Carbon\Carbon::parse($date)->isBetween($start_date, $end_date)) {
                return null;
            }

            return [
                'number' => $payment->number,
                'amount' => $pivot->amount,
                'refunded' => $pivot->refunded,
                'date' => $date,
            ];
        })->filter();

        $this->setPaidRatio($invoice);

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
            'payment_refunded' => $this->payments->sum('refunded'),
            'payment_applied' => $this->payments->sum('amount'),
            'payment_amount' => $this->payments->sum('amount'),
            'event_id' => TransactionEvent::PAYMENT_CASH,
            'timestamp' => now()->timestamp,
            'metadata' => $this->getMetadata($invoice),
            'period' => $end_date,
        ]);
    }

    private function setPaidRatio(Invoice $invoice): self
    {
        if ($invoice->amount == 0) {
            $this->paid_ratio = 0;
            return $this;
        }

        $periodPaid = $this->payments->sum('amount') - $this->payments->sum('refunded');

        $this->paid_ratio = $periodPaid / $invoice->amount;

        return $this;
    }

    private function getMetadata(Invoice $invoice): TransactionEventMetadata
    {

        $calc = $invoice->calc();

        $details = [];

        $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

        foreach ($taxes as $tax) {
            $tax_detail = [
                'tax_name' => $tax['name'],
                'tax_rate' => $tax['tax_rate'],
                'taxable_amount' => ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $this->paid_ratio,
                'tax_amount' => $tax['total'] * $this->paid_ratio,
                'line_total' => ($tax['base_amount'] ?? $calc->getNetSubtotal()),
                'total_tax' => $tax['total'],
                'postal_code' => $invoice->client->postal_code,
            ];
            $details[] = $tax_detail;
        }

        return new TransactionEventMetadata([
            'tax_report' => [
                'tax_details' => $details,
                'tax_details_by_classification' => TaxClassificationCalculator::calculate($invoice, $this->paid_ratio, $details),
                'sales_breakdown' => SalesBreakdownCalculator::calculate($invoice, $this->paid_ratio),
                'payment_history' => $this->payments->toArray(),
                'tax_summary' => [
                    'tax_amount' => $invoice->total_taxes * $this->paid_ratio,
                    'status' => 'updated',
                    'taxable_amount' => $calc->getNetSubtotal() * $this->paid_ratio,
                ],
            ],
        ]);

    }

}
