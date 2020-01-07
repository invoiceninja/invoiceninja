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

namespace App\Jobs\Invoice;

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Client\UpdateClientBalance;
use App\Jobs\Client\UpdateClientPaidToDate;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Jobs\Invoice\ApplyPaymentToInvoice;
use App\Libraries\MultiDB;
use App\Models\Company;
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

    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, Payment $payment, float $amount, Company $company)
    {
        $this->invoice = $invoice;
        $this->payment = $payment;
        $this->amount = $amount;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDB($this->company->db);

        UpdateCompanyLedgerWithPayment::dispatchNow($this->payment, ($this->amount*-1), $this->company);
        UpdateClientBalance::dispatchNow($this->payment->client, $this->amount*-1, $this->company);
        UpdateClientPaidToDate::dispatchNow($this->payment->client, $this->amount, $this->company);

        /* Update Pivot Record amount */
        $this->payment->invoices->each(function ($inv) {
            if ($inv->id == $this->invoice->id) {
                $inv->pivot->amount = $this->amount;
                $inv->pivot->save();
            }
        });

        if ($this->invoice->hasPartial()) {
        //is partial and amount is exactly the partial amount
            if ($this->invoice->partial == $this->amount) {
                $this->invoice->clearPartial();
                $this->invoice->setDueDate();
                $this->invoice->setStatus(Invoice::STATUS_PARTIAL);
                $this->invoice->updateBalance($this->amount*-1);
            } elseif ($this->invoice->partial > 0 && $this->invoice->partial > $this->amount) { //partial amount exists, but the amount is less than the partial amount
                $this->invoice->partial -= $this->amount;
                $this->invoice->updateBalance($this->amount*-1);
            } elseif ($this->invoice->partial > 0 && $this->invoice->partial < $this->amount) { //partial exists and the amount paid is GREATER than the partial amount
                $this->invoice->clearPartial();
                $this->invoice->setDueDate();
                $this->invoice->setStatus(Invoice::STATUS_PARTIAL);
                $this->invoice->updateBalance($this->amount*-1);
            }
        } elseif ($this->amount == $this->invoice->balance) { //total invoice paid.
            $this->invoice->clearPartial();
            //$this->invoice->setDueDate();
            $this->invoice->setStatus(Invoice::STATUS_PAID);
            $this->invoice->updateBalance($this->amount*-1);
        } elseif($this->amount < $this->invoice->balance) { //partial invoice payment made
            $this->invoice->clearPartial();
            $this->invoice->updateBalance($this->amount*-1);
        }
            
        /* Update Payment Applied Amount*/
        $this->payment->applied += $this->amount;
        $this->payment->save();
    }

    
}
