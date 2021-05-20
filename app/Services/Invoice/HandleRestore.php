<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;

class HandleRestore extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    private $payment_total = 0;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {
        if (!$this->invoice->is_deleted) {
            return $this->invoice;
        }

        //determine whether we need to un-delete payments OR just modify the payment amount /applied balances.
        
        foreach ($this->invoice->payments as $payment) {
            //restore the payment record
            $payment->restore();

            //determine the paymentable amount before paymentable restoration
            $pre_restore_amount =    $payment->paymentables()
                                     ->where('paymentable_type', '=', 'invoices')
                                     ->sum(\DB::raw('amount'));

            //restore the paymentables
            $payment->paymentables()
                    ->where('paymentable_type', '=', 'invoices')
                    ->where('paymentable_id', $this->invoice->id)
                    ->restore();

            //determine the post restore paymentable amount (we need to increment the payment amount by the difference between pre and post)
            $payment_amount = $payment->paymentables()
                                     ->where('paymentable_type', '=', 'invoices')
                                     ->sum(\DB::raw('amount'));

            info($payment->amount . " == " . $payment_amount);

            if ($payment->amount == $payment_amount) {
                $payment->is_deleted = false;
                $payment->save();

                $this->payment_total += $payment_amount;
            } else {
                $payment->is_deleted = false;
                $payment->amount += ($payment_amount - $pre_restore_amount);
                $payment->applied += ($payment_amount - $pre_restore_amount);
                $payment->save();

                $this->payment_total += ($payment_amount - $pre_restore_amount);
            }
        }

        //adjust ledger balance
        $this->invoice->ledger()->updateInvoiceBalance($this->invoice->balance, "Restored invoice {$this->invoice->number}")->save();

        //adjust paid to dates
        $this->invoice->client->service()->updatePaidToDate($this->payment_total)->save();

        $this->invoice->client->service()->updateBalance($this->invoice->balance)->save();

        // you only need to touch the ledger ONCE per transaction.
        // $this->invoice->ledger()->updatePaymentBalance($this->payment_total*-1, "Restored payment for invoice {$this->invoice->number}")->save();

        $this->windBackInvoiceNumber();

        return $this->invoice;
    }


    private function windBackInvoiceNumber()
    {
        $findme = '_' . ctrans('texts.deleted');

        $pos = strpos($this->invoice->number, $findme);

        $new_invoice_number = substr($this->invoice->number, 0, $pos);

        if (strlen($new_invoice_number) == 0) {
            $new_invoice_number = null;
        }
        
        try {

            $exists = Invoice::where(['company_id' => $this->invoice->company_id, 'number' => $new_invoice_number])->exists();

            if($exists)
                $this->invoice->number = $this->getNextInvoiceNumber($this->invoice->client, $this->invoice, $this->invoice->recurring_id);
            else
            $this->invoice->number = $new_invoice_number;
            
            $this->invoice->save();
        } catch (\Exception $e) {
            nlog("I could not wind back the invoice number");

            if(Ninja::isHosted()){
                \Sentry\captureMessage("I could not wind back the invoice number");
                app('sentry')->captureException($e);
            }
        }
    }
}
