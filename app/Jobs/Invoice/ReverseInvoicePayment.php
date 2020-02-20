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

use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Utils\Traits\SystemLogTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReverseInvoicePayment implements ShouldQueue
{
    use SystemLogTrait, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payment;
    
    private $company;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(Payment $payment, Company $company)
    {
        $this->payment = $payment;
        $this->company = $company;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle()
    {
        MultiDB::setDB($this->company->db);

        $invoices = $this->payment->invoices()->get();
        $client = $this->payment->client;

        $invoices->each(function ($invoice) {
            if ($invoice->pivot->amount > 0) {
                $invoice->status_id = Invoice::STATUS_SENT;
                $invoice->balance = $invoice->pivot->amount;
                $invoice->save();
            }
        });

        $this->payment->ledger()->updatePaymentBalance($this->payment->amount);

        $client->service()
            ->updateBalance($this->payment->amount)
            ->updatePaidToDate($this->payment->amount*-1)
            ->save();

    }
}
