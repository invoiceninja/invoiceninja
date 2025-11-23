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

        if(!$invoice)
            return;
        
        $this->setPaidRatio($invoice);
        
        $this->payments = $invoice->payments->flatMap(function ($payment) use ($start_date, $end_date) {
            return $payment->invoices()->get()->map(function ($invoice) use ($payment) {
                return [
                    'number' => $payment->number,
                    'amount' => $invoice->pivot->amount,
                    'refunded' => $invoice->pivot->refunded,
                    'date' => $invoice->pivot->created_at->format('Y-m-d'),
                ];
            })->filter(function ($payment) use ($start_date, $end_date) {
                // Filter payments where the pivot created_at is within the date boundaries
                return \Carbon\Carbon::parse($payment['date'])->isBetween($start_date, $end_date);
            });
        });


        TransactionEvent::create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'client_balance' => $invoice->client->balance,
            'client_paid_to_date' => $invoice->client->paid_to_date,
            'client_credit_balance' => $invoice->client->credit_balance,
            'invoice_balance' => $invoice->balance ?? 0,
            'invoice_amount' => $invoice->amount ?? 0  ,
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

        $this->paid_ratio = $invoice->paid_to_date / $invoice->amount;

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
