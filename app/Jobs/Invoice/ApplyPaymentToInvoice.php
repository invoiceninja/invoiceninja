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
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

        $adjustment = $this->payment->amount * -1;

        $partial = max(0, $this->invoice->partial - $this->payment->amount);

        //check if partial exists
        if($this->invoice->partial > 0)
        {

            //if payment amount = partial
            if( $this->formatvalue($this->invoice->partial,4) == $this->formatValue($this->payment->amount,4) )
            {
                $this->invoice->partial = 0;
            }

            //if payment amount < partial amount
            if( $this->formatvalue($this->invoice->partial,4) > $this->formatValue($this->payment->amount,4) )
            {
                //set the new partial amount to the balance
                $this->invoice->partial = $partial;
            }


            $this->partial_due_date = null;
            $this->due_date = 
        }        


        $this->balance = $this->balance + $adjustment;


    }
}
