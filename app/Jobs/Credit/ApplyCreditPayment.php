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

        /* Update Pivot Record amount */
        $this->payment->credits->each(function ($cred) {
            if ($cred->id == $this->credit->id) {
                $cred->pivot->amount = $this->amount;
                $cred->pivot->save();
            }
        });

        $credit_balance = $this->credit->balance*-1;

        if ($this->amount == $credit_balance) { //total invoice paid.
            $this->credit->setStatus(Credit::STATUS_APPLIED);
            $this->credit->updateBalance($this->amount);
        } elseif($this->amount < $credit_balance) { //compare number appropriately
            $this->credit->setStatus(Credit::PARTIAL);
            $this->credit->updateBalance($this->amount);
        }
            
        /* Update Payment Applied Amount*/
        $this->payment->save();
    }

    
}
