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

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ApplyPaymentToInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter;

    public $invoice;

    public $payment;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Payment $payment, Invoice $invoice)
    {

        $this->invoice = $invoice;

        $this->payment = $payment;

    }

    /**
     * Execute the job.
     *
     * 
     * @return void
     */
    public function handle()
    {

        /* The amount we are adjusting the invoice by*/
        $adjustment = $this->payment->amount * -1;

        /* Calculate if the amount paid is less than the partial value.
         * Needed if there is a condition under which a value LESS
         * than the partial amount has been paid. The Invoice will
         *  be updated to reflect the NEW partial amount
         */
        $partial = max(0, $this->invoice->partial - $this->payment->amount);

        /* check if partial exists */
        if($this->invoice->partial > 0)
        {

            //if payment amount = partial
            if( $this->formatvalue($this->invoice->partial,4) == $this->formatValue($this->payment->amount,4) )
            {
                $this->invoice->partial = 0;

                $this->invoice->partial_due_date = null;

            }

            //if payment amount < partial amount
            if( $this->formatvalue($this->invoice->partial,4) > $this->formatValue($this->payment->amount,4) )
            {
                //set the new partial amount to the balance
                $this->invoice->partial = $partial;
            }


            if(!$this->invoice->due_date)
                $this->invoice->due_date = Carbon::now()->addDays(PaymentTerm::find($this->invoice->settings->payment_terms)->num_days);

        }        

        /* Update Invoice Balance */
        $this->invoice->balance = $this->invoice->balance + $adjustment;

        /* Update Invoice Status */
        if($this->invoice->balance == 0)
            $this->invoice->status_id = Invoice::STATUS_PAID;
        elseif($this->payment->amount > 0 && $this->invoice->balance > 0)
            $this->invoice->status_id = Invoice::STATUS_PARTIAL;

        /*If auto-archive is enabled, and balance = 0 - archive invoice */
        if($this->invoice->settings->auto_archive_invoice && $this->invoice->balance == 0)
        {
            $invoiceRepo = app('App\Repositories\InvoiceRepository');
            $invoiceRepo->archive($this->invoice);
        }

        $this->invoice->save();

        return $this->invoice;
    }
}
