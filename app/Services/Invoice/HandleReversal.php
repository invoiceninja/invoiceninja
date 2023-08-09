<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasReversed;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;

class HandleReversal extends AbstractService
{
    use GeneratesCounter;

    public function __construct(private Invoice $invoice)
    {
    }

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
                                    ->get();

        $paymentables->each(function ($paymentable) use ($total_paid) {
            //new concept - when reversing, we unwind the payments
            $payment = Payment::withTrashed()->find($paymentable->payment_id);

            $reversable_amount = $paymentable->amount - $paymentable->refunded;
            $total_paid -= $reversable_amount;

            $payment->applied -= $reversable_amount;
            $payment->save();

            $paymentable->amount = $paymentable->refunded;
            $paymentable->save();
        });

        /* Generate a credit for the $total_paid amount */
        $notes = 'Credit for reversal of '.$this->invoice->number;

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
