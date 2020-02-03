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
use App\Factory\CreditFactory;
use App\Factory\PaymentFactory;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Credit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkCreditPaid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $credit;

    private $company;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Credit $credit, Company $company)
    {
        $this->credit = $credit;
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

        if($this->credit->status_id == Credit::STATUS_DRAFT)
            $this->credit->markSent();

        /* Create Payment */
        $payment = PaymentFactory::create($this->credit->company_id, $this->credit->user_id);

        $payment->amount = $this->credit->balance;
        $payment->status_id = Credit::STATUS_COMPLETED;
        $payment->client_id = $this->credit->client_id;
        $payment->transaction_reference = ctrans('texts.manual_entry');
        /* Create a payment relationship to the invoice entity */
        $payment->save();

        $payment->credits()->attach($this->credit->id, [
            'amount' => $payment->amount
        ]);

        $this->credit->updateBalance($payment->amount*-1);

        /* Update Credit balance */
        event(new PaymentWasCreated($payment, $payment->company));

        // UpdateCompanyLedgerWithPayment::dispatchNow($payment, ($payment->amount*-1), $this->company);

        return $this->credit;
    }
}
