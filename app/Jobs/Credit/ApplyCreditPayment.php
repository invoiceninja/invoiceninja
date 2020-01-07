<?php
/**
 * Credit Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Credit;

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Client\UpdateClientBalance;
use App\Jobs\Client\UpdateClientPaidToDate;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Jobs\Credit\ApplyPaymentToCredit;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Payment;
use App\Repositories\CreditRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplyCreditPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $credit;

    public $payment;

    public $amount;

    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Credit $credit, Payment $payment, float $amount, Company $company)
    {
        $this->credit = $credit;
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
        $this->payment->credits->each(function ($cred) {
            if ($cred->id == $this->credit->id) {
                $cred->pivot->amount = $this->amount;
                $cred->pivot->save();
            }
        });

        if ($this->credit->hasPartial()) {
        //is partial and amount is exactly the partial amount
            if ($this->credit->partial == $this->amount) {
                $this->credit->clearPartial();
                $this->credit->setDueDate();
                $this->credit->setStatus(Credit::STATUS_PARTIAL);
                $this->credit->updateBalance($this->amount*-1);
            } elseif ($this->credit->partial > 0 && $this->credit->partial > $this->amount) { //partial amount exists, but the amount is less than the partial amount
                $this->credit->partial -= $this->amount;
                $this->credit->updateBalance($this->amount*-1);
            } elseif ($this->credit->partial > 0 && $this->credit->partial < $this->amount) { //partial exists and the amount paid is GREATER than the partial amount
                $this->credit->clearPartial();
                $this->credit->setDueDate();
                $this->credit->setStatus(Credit::STATUS_PARTIAL);
                $this->credit->updateBalance($this->amount*-1);
            }
        } elseif ($this->amount == $this->credit->balance) { //total invoice paid.
            $this->credit->clearPartial();
            //$this->credit->setDueDate();
            $this->credit->setStatus(Credit::STATUS_PAID);
            $this->credit->updateBalance($this->amount*-1);
        } elseif($this->amount < $this->credit->balance) { //partial invoice payment made
            $this->credit->clearPartial();
            $this->credit->updateBalance($this->amount*-1);
        }
            
        /* Update Payment Applied Amount*/
        $this->payment->applied += $this->amount;
        $this->payment->save();
    }

    
}
