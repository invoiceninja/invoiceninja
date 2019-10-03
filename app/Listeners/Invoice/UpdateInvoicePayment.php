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
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Models\SystemLog;
use App\Utils\Traits\SystemLogTrait;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateInvoicePayment implements ShouldQueue
{
    use SystemLogTrait;

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
            $invoices->each(function ($invoice) use($payment){
                
                UpdateCompanyLedgerWithPayment::dispatchNow($payment, ($invoice->balance*-1));
                $invoice->clearPartial();
                $invoice->updateBalance($invoice->balance*-1);
            
            });

        }
        else {
            
            $total = 0;

            foreach($invoice as $invoice)
            {

                if($invoice->hasPartial())
                    $total += $invoice->partial;
                else
                    $total += $invoice->balance;

                Log::error("total = {$total}");
            }

            /* test if there is a batch of partial invoices that have been paid */
            if($payment->amount == $total)
            {
                
                $invoices->each(function ($invoice) use($payment){

                    if($invoice->hasPartial()) {

                        UpdateCompanyLedgerWithPayment::dispatchNow($payment, ($invoice->partial*-1));
                        $invoice->updateBalance($invoice->partial*-1);
                        $invoice->clearPartial();
                        $invoice->setDueDate();
                        //todo do we need to mark it as a partial?
                    }
                    else
                    {
                        UpdateCompanyLedgerWithPayment::dispatchNow($payment, ($invoice->balance*-1));
                        $invoice->clearPartial();
                        $invoice->updateBalance($invoice->balance*-1);
                    }

                });

            }
            else {

                $data = [
                    'payment' => $payment,
                    'invoices' => $invoices,
                    'invoices_total' => $invoices_total,
                    'payment_amount' => $payment->amount,
                    'partial_check_amount' => $total,
                ];


                $this->sysLog($data, SystemLog::GATEWAY_RESPONSE, SystemLog::PAYMENT_RECONCILIATION_FAILURE);

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