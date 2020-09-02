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


namespace App\Services\Payment;

use App\Helpers\Email\PaymentEmail;
use App\Jobs\Payment\EmailPayment;
use App\Jobs\Util\SystemLogger;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;

class UpdateInvoicePayment
{
    use MakesHash;

    /**
     * @deprecated This is bad logic, assumes too much.
     */
    public $payment;

    public $payment_hash;

    public function __construct($payment, $payment_hash)
    {
        $this->payment = $payment;
        $this->payment_hash = $payment_hash;
    }

    public function run()
    {
        $paid_invoices = $this->payment_hash->invoices();
        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($paid_invoices, 'invoice_id')))->get();

        collect($paid_invoices)->each(function ($paid_invoice) use($invoices) {

            $invoice = $invoices->first(function ($inv) use($paid_invoice) {
                return $paid_invoice->invoice_id == $inv->hashed_id;
            });

            if($invoice->id == $this->payment_hash->fee_invoice_id)
                $paid_amount = $paid_invoice->amount + $this->payment_hash->fee_total;
            else
                $paid_amount = $paid_invoice->amount;

            $this->payment
                 ->ledger()
                 ->updatePaymentBalance($paid_amount*-1);

            $this->payment
                ->client
                ->service()
                ->updateBalance($paid_amount*-1)
                ->updatePaidToDate($paid_amount)
                ->save();

            $pivot_invoice = $this->payment->invoices->first(function ($inv) use($paid_invoice){
                    return $inv->hashed_id == $paid_invoice->invoice_id;
            });

            /*update paymentable record*/
            $pivot_invoice->pivot->amount = $paid_amount;
            $pivot_invoice->save();


            $invoice->service() //caution what if we amount paid was less than partial - we wipe it! 
                ->clearPartial()
                ->updateBalance($paid_amount*-1)
                ->save();

        });

            // } else {
            //     SystemLogger::dispatch(
            //         [
            //             'payment' => $this->payment,
            //             'invoices' => $invoices,
            //             'invoices_total' => $invoices_total,
            //             'payment_amount' => $this->payment->amount,
            //             'partial_check_amount' => $total,
            //         ],
            //         SystemLog::CATEGORY_GATEWAY_RESPONSE,
            //         SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE,
            //         SystemLog::TYPE_LEDGER,
            //         $this->payment->client
            //     );

            //     throw new \Exception("payment amount {$this->payment->amount} does not match invoice totals {$invoices_total} reversing payment");

            //     $this->payment->invoice()->delete();
            //     $this->payment->is_deleted=true;
            //     $this->payment->save();
            //     $this->payment->delete();
            // }
        

        return $this->payment;
    }
}
