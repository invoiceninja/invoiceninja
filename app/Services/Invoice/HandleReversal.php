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

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasReversed;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Str;

class HandleReversal extends AbstractService
{
    use GeneratesCounter;

    public function __construct(private Invoice $invoice) {}

    public function run()
    {
        /* Check again!! */
        if (! $this->invoice->invoiceReversable($this->invoice)) {
            return $this->invoice;
        }

        /* If the invoice has been cancelled - we need to unwind the cancellation before reversing*/
        if ($this->invoice->status_id == Invoice::STATUS_CANCELLED) {
            $this->invoice = $this->invoice->service()->reverseCancellation()->save();
        }

        $balance_remaining = $this->invoice->balance;

        $total_paid = $this->invoice->amount - $this->invoice->balance;

        /*Adjust payment applied and the paymentables to the correct amount */
        $paymentables = Paymentable::query()->wherePaymentableType('invoices')
                                    ->wherePaymentableId($this->invoice->id)
                                    ->with(['payment' => fn ($query) => $query->withTrashed()])
                                    ->get();
        $effective_date = now($this->invoice->company->timezone()?->name ?: config('app.timezone'))->toDateString();
        $mutation_key = 'invoice_reversed:'.Str::uuid();
        $tax_event_snapshots = [];

        $paymentables->each(function (Paymentable $paymentable) use ($total_paid, $effective_date, $mutation_key, &$tax_event_snapshots): void {
            //new concept - when reversing, we unwind the payments
            $payment = Payment::withTrashed()->find($paymentable->payment_id);

            $reversable_amount = $paymentable->amount - $paymentable->refunded;
            $total_paid -= $reversable_amount;

            if ($payment && abs((float) $reversable_amount) >= 0.0001) {
                $source = app(InvoiceTransactionEventEntryCash::class)
                    ->runForPaymentable($this->invoice, $paymentable);

                if ($source) {
                    $tax_event_snapshots[] = [
                        'source_event_id' => $source->id,
                        'paymentable_id' => $paymentable->id,
                        'amount' => abs((float) $reversable_amount),
                        'effective_date' => $effective_date,
                        'correction_key' => sha1("{$mutation_key}|{$paymentable->id}"),
                    ];
                }
            }

            $payment->applied -= $reversable_amount;
            $payment->save();

            $paymentable->amount = $paymentable->refunded;
            $paymentable->save();
        });

        foreach ($tax_event_snapshots as $snapshot) {
            $source = TransactionEvent::query()->find($snapshot['source_event_id']);

            if (! $source) {
                throw new \RuntimeException('Invoice reversal tax source event is unavailable.');
            }

            app(InvoiceTransactionEventEntryCash::class)->writeCorrection(
                source: $source,
                effective_date: $snapshot['effective_date'],
                sign: -1,
                kind: 'payment_deleted',
                correction_key: $snapshot['correction_key'],
                context: [
                    'mutation_key' => $mutation_key,
                    'mutation_type' => 'invoice_reversed',
                    'invoice_id' => $this->invoice->id,
                ],
                amount: $snapshot['amount'],
            );
        }

        /* Generate a credit for the $total_paid amount */
        $notes = 'Credit for reversal of ' . $this->invoice->number;

        /* Set invoice balance to 0 */
        if ($this->invoice->balance != 0) {
            $this->invoice->ledger()->updateInvoiceBalance($balance_remaining * -1, $notes)->save();
        }

        $this->invoice->balance = 0;
        $this->invoice->paid_to_date = 0;

        /* Set invoice status to reversed... somehow*/
        $this->invoice->service()->setStatus(Invoice::STATUS_REVERSED)->save();

        /* Reduce client.paid_to_date by $total_paid amount */
        /* Reduce the client balance by $balance_remaining */

        $this->invoice->client->service()
            ->updateBalance($balance_remaining * -1)
            ->save();

        event(new InvoiceWasReversed($this->invoice, $this->invoice->company, Ninja::eventVars()));

        return $this->invoice;

    }
}
