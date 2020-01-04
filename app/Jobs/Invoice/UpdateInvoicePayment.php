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

use App\Jobs\Client\UpdateClientBalance;
use App\Jobs\Client\UpdateClientPaidToDate;
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Utils\Traits\SystemLogTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateInvoicePayment implements ShouldQueue
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

        $invoices_total = $invoices->sum('balance');

        /* Simplest scenario - All invoices are paid in full*/
        if (strval($invoices_total) === strval($this->payment->amount)) {
            $invoices->each(function ($invoice) {
                UpdateCompanyLedgerWithPayment::dispatchNow($this->payment, ($invoice->balance*-1), $this->company);
                UpdateClientBalance::dispatchNow($this->payment->client, $invoice->balance*-1, $this->company);
                UpdateClientPaidToDate::dispatchNow($this->payment->client, $invoice->balance, $this->company);

                $invoice->pivot->amount = $invoice->balance;
                $invoice->pivot->save();

                $invoice->clearPartial();
                $invoice->updateBalance($invoice->balance*-1);
            });
        }
        /*Combination of partials and full invoices are being paid*/
        else {
            $total = 0;

            /* Calculate the grand total of the invoices*/
            foreach ($invoices as $invoice) {
                if ($invoice->hasPartial()) {
                    $total += $invoice->partial;
                } else {
                    $total += $invoice->balance;
                }
            }

            /*Test if there is a batch of partial invoices that have been paid */
            if ($this->payment->amount == $total) {
                $invoices->each(function ($invoice) {
                    if ($invoice->hasPartial()) {
                        UpdateCompanyLedgerWithPayment::dispatchNow($this->payment, ($invoice->partial*-1), $this->company);
                        UpdateClientBalance::dispatchNow($this->payment->client, $invoice->partial*-1, $this->company);
                        UpdateClientPaidToDate::dispatchNow($this->payment->client, $invoice->partial, $this->company);
                        $invoice->pivot->amount = $invoice->partial;
                        $invoice->pivot->save();

                        $invoice->updateBalance($invoice->partial*-1);
                        $invoice->clearPartial();
                        $invoice->setDueDate();
                        $invoice->setStatus(Invoice::STATUS_PARTIAL);
                    } else {
                        UpdateCompanyLedgerWithPayment::dispatchNow($this->payment, ($invoice->balance*-1), $this->company);
                        UpdateClientBalance::dispatchNow($this->payment->client, $invoice->balance*-1, $this->company);
                        UpdateClientPaidToDate::dispatchNow($this->payment->client, $invoice->balance, $this->company);

                        $invoice->pivot->amount = $invoice->balance;
                        $invoice->pivot->save();
                        
                        $invoice->clearPartial();
                        $invoice->updateBalance($invoice->balance*-1);
                    }
                });
            } else {
                SystemLogger::dispatch(
                    [
                        'payment' => $this->payment,
                        'invoices' => $invoices,
                        'invoices_total' => $invoices_total,
                        'payment_amount' => $this->payment->amount,
                        'partial_check_amount' => $total,
                    ],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE,
                    SystemLog::TYPE_LEDGER,
                    $this->payment->client
                );

                throw new \Exception("payment amount {$this->payment->amount} does not match invoice totals {$invoices_total} reversing payment");

                $this->payment->invoice()->delete();
                $this->payment->is_deleted=true;
                $this->payment->save();
                $this->payment->delete();
            }
        }
    }
}

/*
        $this->payment = $event->payment;
        $invoice = $this->payment->invoice;
        $adjustment = $this->payment->amount * -1;
        $partial = max(0, $invoice->partial - $this->payment->amount);

        $invoice->updateBalances($adjustment, $partial);
        $invoice->updatePaidStatus(true);

        // store a backup of the invoice
        $activity = Activity::wherePaymentId($this->payment->id)
                        ->whereActivityTypeId(ACTIVITY_TYPE_CREATE_PAYMENT)
                        ->first();
        $activity->json_backup = $invoice->hidePrivateFields()->toJSON();
        $activity->save();

        if ($invoice->balance == 0 && $this->payment->account->auto_archive_invoice) {
            $invoiceRepo = app('App\Ninja\Repositories\InvoiceRepository');
            $invoiceRepo->archive($invoice);
        }
*/
