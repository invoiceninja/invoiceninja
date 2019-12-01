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

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Client\UpdateClientBalance;
use App\Jobs\Client\UpdateClientPaidToDate;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Jobs\Invoice\ApplyPaymentToInvoice;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\InvoiceRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplyInvoicePayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    public $payment;

    public $amount;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, Payment $payment, float $amount)
    {

        $this->invoice = $invoice;
        $this->payment = $payment;
        $this->amount = $amount;

    }

    /**
     * Execute the job.
     *
     * 
     * @return void
     */
    public function handle()
    {
\Log::error("invoice = ".$this->invoice->id);
\Log::error("total payment amount = ".$this->payment->amount);
\Log::error("invoice amount paid = ".$this->amount);

        UpdateCompanyLedgerWithPayment::dispatchNow($this->payment, ($this->amount*-1));
        UpdateClientBalance::dispatchNow($this->payment->client, $this->amount*-1);
        UpdateClientPaidToDate::dispatchNow($this->payment->client, $this->amount);

        /* Update Pivot Record amount */
        $this->payment->invoices->each(function ($inv){

            if($inv->id == $this->invoice->id){
                \Log::error("found the pivot with ID of ".$inv->id. " setting ". $this->amount ." to the pivot field which is currently ".$inv->pivot->amount);
                $inv->pivot->amount = $this->amount;
                $inv->pivot->save();
            }

        });

        // $this->invoice->pivot->amount = $this->amount;
        // $this->invoice->pivot->save();

        if($this->invoice->hasPartial())
        {
            \Log::error("found has partial");
            //is partial and amount is exactly the partial amount
            if($this->invoice->partial == $this->amount)
            {
            \Log::error("partial == amount");

                $this->invoice->clearPartial();
                $this->invoice->setDueDate();
                $this->invoice->setStatus(Invoice::STATUS_PARTIAL);
                $this->invoice->updateBalance($this->amount*-1);
            }
            elseif($this->invoice->partial > 0 && $this->invoice->partial > $this->amount) //partial amount exists, but the amount is less than the partial amount
            {
            \Log::error("partial > amount");
                $this->invoice->partial -= $this->amount;
                $this->invoice->updateBalance($this->amount*-1);
            }
            elseif($this->invoice->partial > 0 && $this->invoice->partial < $this->amount) //partial exists and the amount paid is GREATER than the partial amount
            {
            \Log::error("partial < amount");
                $this->invoice->clearPartial();
                $this->invoice->setDueDate();
                $this->invoice->setStatus(Invoice::STATUS_PARTIAL);
                $this->invoice->updateBalance($this->amount*-1);
            }

        }
        elseif($this->invoice->amount == $this->invoice->balance) //total invoice paid.
        {
            \Log::error("balance == amount");

            $this->invoice->clearPartial();
            $this->invoice->setDueDate();
            $this->invoice->setStatus(Invoice::STATUS_PAID);
            $this->invoice->updateBalance($this->amount*-1);
        }


    }
}