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
    
	public function save(Request $request, Payment $payment) : ?Payment
	{

        $payment->fill($request->input());

        $payment->save();
        
        if($request->input('invoices')) 
        {

            $invoices = Invoice::whereIn('id', array_column($request->input('invoices'),'id'))->company()->get();

            $payment->invoices()->saveMany($invoices);
    
            foreach($request->input('invoices') as $paid_invoice)
            {

                $invoice = Invoice::whereId($paid_invoice['id'])->company()->first();

                if($invoice)
                    ApplyInvoicePayment::dispatchNow($invoice, $payment, $paid_invoice['amount']);

            }

        }
        else {
            //paid is made, but not to any invoice, therefore we are applying the payment to the clients credit
            ApplyClientPayment::dispatchNow($payment);
        }

        event(new PaymentWasCreated($payment));

        //UpdateInvoicePayment::dispatchNow($payment);

        return $payment->fresh();

	}

}