<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Payment;

use App\Events\Invoice\InvoiceWasUpdated;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

class UpdateInvoicePayment
{
    use MakesHash;

    public $payment;

    public $payment_hash;

    public function __construct(Payment $payment, PaymentHash $payment_hash)
    {
        $this->payment = $payment;
        $this->payment_hash = $payment_hash;
    }

    public function run()
    {
        $paid_invoices = $this->payment_hash->invoices();

        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($paid_invoices, 'invoice_id')))->get();

        collect($paid_invoices)->each(function ($paid_invoice) use ($invoices) {
            $invoice = $invoices->first(function ($inv) use ($paid_invoice) {
                return $paid_invoice->invoice_id == $inv->hashed_id;
            });

            if ($invoice->id == $this->payment_hash->fee_invoice_id) {
                $paid_amount = $paid_invoice->amount + $this->payment_hash->fee_total;
            } else {
                $paid_amount = $paid_invoice->amount;
            }

            $this->payment
                 ->ledger()
                 ->updatePaymentBalance($paid_amount * -1);

            $this->payment
                ->client
                ->service()
                ->updateBalance($paid_amount * -1)
                ->updatePaidToDate($paid_amount)
                ->save();

            $pivot_invoice = $this->payment->invoices->first(function ($inv) use ($paid_invoice) {
                return $inv->hashed_id == $paid_invoice->invoice_id;
            });

            /*update paymentable record*/
            $pivot_invoice->pivot->amount = $paid_amount;
            $pivot_invoice->pivot->save();

            $this->payment->applied += $paid_amount;

            $invoice->service() //caution what if we amount paid was less than partial - we wipe it!
                ->clearPartial()
                ->updateBalance($paid_amount * -1)
                ->updateStatus()
                ->save();

            event(new InvoiceWasUpdated($invoice, $invoice->company, Ninja::eventVars()));
        });
        
        $this->payment->save();

        return $this->payment;
    }
}
