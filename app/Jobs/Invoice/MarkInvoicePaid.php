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

class MarkInvoicePaid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    private $company;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, Company $company)
    {

        $this->invoice = $invoice;
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

        
        /* Create Payment */
        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $this->invoice->balance;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $this->invoice->client_id;
        $payment->transaction_reference = ctrans('texts.manual_entry');
        /* Create a payment relationship to the invoice entity */
        $payment->save();

        $payment->invoices()->attach($this->invoice->id,[
            'amount' => $payment->amount
        ]);

        $this->invoice->updateBalance($payment->amount*-1);

        /* Update Invoice balance */
        event(new PaymentWasCreated($payment, $payment->company));

        UpdateCompanyLedgerWithPayment::dispatchNow($payment, ($payment->amount*-1));
        UpdateClientBalance::dispatchNow($payment->client, $payment->amount*-1);
        UpdateClientPaidToDate::dispatchNow($payment->client, $payment->amount);

        return $this->invoice;
    }
}
