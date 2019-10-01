<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Invoice;

use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Models\SystemLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateInvoicePayment implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $payment = $event->payment;
        $invoices = $payment->invoices();

        $invoices_total = $invoices->sum('balance');

        /* Simplest scenario*/
        if($invoices_total == $payment->amount)
        {

            $invoices->each(function ($invoice){
                $invoice->updateBalance($invoice->balance*-1);
                UpdateCompanyLedgerWithInvoice::dispatchNow($invoice, ($invoice->balance*-1));
            });

        }
        else {

            $total = 0;

            foreach($invoice as $invoice)
            {

                if($invoice->isPartial())
                    $total += $invoice->partial;
                else
                    $total += $invoice->balance;

            }

            /* test if there is a batch of partial invoices that have been paid */
            if($payment->amount == $total)
            {
                    //process invoices and update balance depending on
                    //whether the invoice balance or partial amount was
                    //paid
            }
            else {

                $data = [
                    'payment' => $payment,
                    'invoices' => $invoices,
                    'invoices_total' => $invoices_total,
                    'payment_amount' => $payment->amount,
                    'partial_check_amount' => $total,
                ];

                $sl = [
                    'client_id' => $payment->client_id,
                    'user_id' => $payment->user_id,
                    'company_id' => $payment->company_id,
                    'log' => $data,
                    'category_id' => SystemLog::PAYMENT_RESPONSE,
                    'event_id' => SystemLog::PAYMENT_RECONCILIATION_FAILURE,
                ]

                SystemLog::create($sl);

                throw new Exception('payment amount does not match invoice totals');
            }


        }
    }
}    

/*
        $payment = $event->payment;
        $invoice = $payment->invoice;
        $adjustment = $payment->amount * -1;
        $partial = max(0, $invoice->partial - $payment->amount);

        $invoice->updateBalances($adjustment, $partial);
        $invoice->updatePaidStatus(true);

        // store a backup of the invoice
        $activity = Activity::wherePaymentId($payment->id)
                        ->whereActivityTypeId(ACTIVITY_TYPE_CREATE_PAYMENT)
                        ->first();
        $activity->json_backup = $invoice->hidePrivateFields()->toJSON();
        $activity->save();

        if ($invoice->balance == 0 && $payment->account->auto_archive_invoice) {
            $invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');
            $invoiceRepo->archive($invoice);
        }
*/