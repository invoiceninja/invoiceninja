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
    public function run($invoice, $start_date, $end_date)
    {

        if (!$invoice) {
            return;
        }

        $this->payments = $invoice->payments->map(function ($payment) use ($invoice, $start_date, $end_date) {
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

    private function getMetadata($invoice)
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
