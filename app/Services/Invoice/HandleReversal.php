<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasReversed;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Ninja\TransactionLog;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;

class HandleReversal extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
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
        $paymentables = Paymentable::wherePaymentableType('invoices')
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
        $credit = false;

        // if ($total_paid > 0) {

        //     $credit = CreditFactory::create($this->invoice->company_id, $this->invoice->user_id);
        //     $credit->client_id = $this->invoice->client_id;
        //     $credit->invoice_id = $this->invoice->id;
        //     $credit->date = now();

        //     $item = InvoiceItemFactory::create();
        //     $item->quantity = 1;
        //     $item->cost = (float) $total_paid;
        //     $item->notes = $notes;

        //     $line_items[] = $item;
        //     $credit->line_items = $line_items;
        //     $credit->save();

        //     $credit_calc = new InvoiceSum($credit);
        //     $credit_calc->build();

        //     $credit = $credit_calc->purgeTaxes()->getCredit();
        //     $credit->service()->markSent()->save();
        // }

        /*If there is a payment linked, then the credit needs to be linked back to that payment in case of refund*/
        if ($paymentables->count() > 0 && $credit) {
            // $payment = $paymentables->first()->payment;
            // $payment->credits()->save($credit);

            // $paymentable_credit = $payment->credits()
            //                               ->wherePaymentableType(Credit::class)
            //                               ->wherePaymentableId($credit->id)
            //                               ->first();

            // //harvest the credit record and add in the amount for the credit.
            // $paymentable_credit->pivot->amount = $total_paid;
            // $paymentable_credit->pivot->save();

            // $paymentable_credit->paid_to_date += $total_paid;
            // $paymentable_credit->save();
        }

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
            // ->updatePaidToDate($total_paid * -1)
            ->save();

        event(new InvoiceWasReversed($this->invoice, $this->invoice->company, Ninja::eventVars()));

        $transaction = [
            'invoice' => $this->invoice->transaction_event(),
            'payment' => [],
            'client' => $this->invoice->client->transaction_event(),
            'credit' => [],
            'metadata' => [],
        ];

        TransactionLog::dispatch(TransactionEvent::INVOICE_REVERSED, $transaction, $this->invoice->company->db);

        return $this->invoice;
        //create a ledger row for this with the resulting Credit ( also include an explanation in the notes section )
    }
}
