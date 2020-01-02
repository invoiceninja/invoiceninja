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

namespace App\Repositories;

use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Jobs\Invoice\UpdateInvoicePayment;
use App\Jobs\Invoice\ApplyInvoicePayment;
use App\Jobs\Invoice\ApplyClientPayment;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * PaymentRepository
 */
class PaymentRepository extends BaseRepository
{
    public function getClassName()
    {
        return Payment::class;
    }
    
    /**
     * Saves a payment.
     * 
     * 
     * @param  Request $request the request object
     * @param  Payment $payment The Payment object
     * @return Object       Payment $payment
     */
    public function save(Request $request, Payment $payment) : ?Payment
    {
        //todo this save() only works for new payments... will fail if a Payment is updated and saved through here.
        $payment->fill($request->input());

        $payment->save();
        
        if ($request->has('invoices')) {
            $invoices = Invoice::whereIn('id', array_column($request->input('invoices'), 'id'))->company()->get();

            $payment->invoices()->saveMany($invoices);
    
            foreach ($request->input('invoices') as $paid_invoice) {
                $invoice = Invoice::whereId($paid_invoice['id'])->company()->first();

                if ($invoice) {
                    ApplyInvoicePayment::dispatchNow($invoice, $payment, $paid_invoice['amount'], $invoice->company);
                }
            }
        } else {
            //payment is made, but not to any invoice, therefore we are applying the payment to the clients credit
            ApplyClientPayment::dispatchNow($payment, $payment->company);
        }

        event(new PaymentWasCreated($payment, $payment->company));

        //UpdateInvoicePayment::dispatchNow($payment);

        return $payment->fresh();
    }

    /**
     * Updates
     *
     * The update code path is different to the save path
     * additional considerations come into play when 'updating'
     * a payment.
     * 
     * @param  Request $request the request object
     * @param  Payment $payment The Payment object
     * @return Object       Payment $payment
     */
    public function update(Request $request, Payment $payment) :?Payment
    {
        
        if($payment->amount >= 0)
            return $this->applyPayment($request, $payment);
        
        return $this->refundPayment($request, $payment);

    }

    private function applyPayment(Request $request, Payment $payment) :?Payment
    {

    }

    private function refundPayment(Request $request, Payment $payment) :?Payment
    {

        $invoice_total_adjustment = 0;

        if($request->has('invoices')){
            
            foreach($request->input('invoices') as $adjusted_invoice) {
                //$invoice = Invoice::whereId($adjusted_invoice['id'])->company()->first();
                $invoice_total_adjustment += $adjusted_invoice['amount'];

                //todo - generate Credit Note for $amount on $invoice
            }

            if($request->input('amount') != $invoice_total_adjustment)
                return 'Amount must equal the sum of invoice adjustments';
        }


        //adjust applied amount
        $payment->applied += $invoice_total_adjustment;

        //adjust clients paid to date
        $client = $payment->client;
        $client->paid_to_date += $invoice_total_adjustment;
    
    }

    
}
