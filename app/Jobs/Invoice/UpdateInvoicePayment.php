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
     * @deprecated we only use this in test data creation. shouldn't be used in production
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
                
                $this->payment
                     ->ledger()
                     ->updatePaymentBalance($this->payment, ($invoice->balance*-1));
                
                $this->payment->client
                    ->service()
                    ->updateBalance($invoice->balance*-1)
                    ->updatePaidToDate($invoice->balance)
                    ->save();
                
                $invoice->pivot->amount = $invoice->balance;
                $invoice->pivot->save();

                $invoice->service()
                    ->clearPartial()
                    ->updateBalance($invoice->balance*-1)
                    ->save();
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

                        $this->payment
                             ->ledger()
                             ->updatePaymentBalance($this->payment, ($invoice->partial*-1));

                        $this->payment->client->service()
                                                ->updateBalance($invoice->partial*-1)
                                                ->updatePaidToDate($invoice->partial)
                                                ->save();

                        $invoice->pivot->amount = $invoice->partial;
                        $invoice->pivot->save();

                        $invoice->service()->updateBalance($invoice->partial*-1)
                                ->clearPartial()
                                ->setDueDate()
                                ->setStatus(Invoice::STATUS_PARTIAL)
                                ->save();
                    } else {
                        
                        $this->payment
                             ->ledger()
                             ->updatePaymentBalance($this->payment, ($invoice->balance*-1));

                        $this->payment->client->service()
                                              ->updateBalance($invoice->balance*-1)
                                              ->updatePaidToDate($invoice->balance)
                                              ->save();

                        $invoice->pivot->amount = $invoice->balance;
                        $invoice->pivot->save();
                        
                        $invoice->service()->clearPartial()->updateBalance($invoice->balance*-1)->save();
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
