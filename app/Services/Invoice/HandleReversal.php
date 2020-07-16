<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasReversed;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
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
        if (!$this->invoice->invoiceReversable($this->invoice)) {
            return $this->invoice;
        }

        /* If the invoice has been cancelled - we need to unwind the cancellation before reversing*/
        if($this->invoice->status_id == Invoice::STATUS_CANCELLED)
            $this->invoice = $this->invoice->service()->reverseCancellation()->save();

        $balance_remaining = $this->invoice->balance;

        $total_paid = $this->invoice->amount - $this->invoice->balance;

        /*Adjust payment applied and the paymentables to the correct amount */
        $paymentables = Paymentable::wherePaymentableType(Invoice::class)
                                    ->wherePaymentableId($this->invoice->id)
                                    ->get();

        $paymentables->each(function ($paymentable) use ($total_paid) {
            $reversable_amount = $paymentable->amount - $paymentable->refunded;

            $total_paid -= $reversable_amount;

            $paymentable->amount = $paymentable->refunded;
            $paymentable->save();
        });

        /* Generate a credit for the $total_paid amount */
        $notes = "Credit for reversal of ".$this->invoice->number;

        if ($total_paid > 0) {
            $credit = CreditFactory::create($this->invoice->company_id, $this->invoice->user_id);
            $credit->client_id = $this->invoice->client_id;
            $credit->invoice_id = $this->invoice->id;
            
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            $item->cost = (float)$total_paid;
            $item->notes = $notes;

            $line_items[] = $item;

            $credit->line_items = $line_items;

            $credit->save();

            $credit_calc = new InvoiceSum($credit);
            $credit_calc->build();

            $credit = $credit_calc->getCredit();

            $credit->service()->markSent()->save();
        }

        /* Set invoice balance to 0 */
        if($this->invoice->balance != 0)
            $this->invoice->ledger()->updateInvoiceBalance($balance_remaining*-1, $notes)->save();

        $this->invoice->balance=0;

        /* Set invoice status to reversed... somehow*/
        $this->invoice->service()->setStatus(Invoice::STATUS_REVERSED)->save();

        /* Reduce client.paid_to_date by $total_paid amount */
        /* Reduce the client balance by $balance_remaining */

        $this->invoice->client->service()
            ->updateBalance($balance_remaining*-1)
            ->updatePaidToDate($total_paid*-1)
            ->save();

        event(new InvoiceWasReversed($this->invoice, $this->invoice->company, Ninja::eventVars()));
        
        return $this->invoice;
        //create a ledger row for this with the resulting Credit ( also include an explanation in the notes section )
    }


    // public function run2()
    // {

    //     /* Check again!! */
    //     if (!$this->invoice->invoiceReversable($this->invoice)) {
    //         return $this->invoice;
    //     }

    //     if($this->invoice->status_id == Invoice::STATUS_CANCELLED)
    //         $this->invoice = $this->invoice->service()->reverseCancellation()->save();

    //     //$balance_remaining = $this->invoice->balance;

    //     //$total_paid = $this->invoice->amount - $this->invoice->balance;

    //     /*Adjust payment applied and the paymentables to the correct amount */
    //     $paymentables = Paymentable::wherePaymentableType(Invoice::class)
    //                                 ->wherePaymentableId($this->invoice->id)
    //                                 ->get();

    //     $total_paid = 0;

    //     $paymentables->each(function ($paymentable) use ($total_paid) {

    //         $reversable_amount = $paymentable->amount - $paymentable->refunded;
    //         $total_paid -= $reversable_amount;
    //         $paymentable->amount = $paymentable->refunded;
    //         $paymentable->save();
    //     });

    //     //Unwinding any payments made to this invoice
        
        
    // }
}

    