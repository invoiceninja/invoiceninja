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
use App\Models\Payment;
use App\Services\AbstractService;

class ApplyPayment extends AbstractService
{
    private $invoice;

    private $payment;

    private $payment_amount;

    public function __construct($invoice, $payment, $payment_amount)
    {
        $this->invoice = $invoice;
        $this->payment = $payment;
        $this->payment_amount = $payment_amount;
    }

    /* Apply payment to a single invoice */
    public function run()
    {

        $this->invoice->fresh('client');
 
        $amount_paid = 0;

        if ($this->invoice->hasPartial()) {
            
            if ($this->invoice->partial == $this->payment_amount) 
            {

                //is partial and amount is exactly the partial amount

                $amount_paid = $this->payment_amount * -1;

                $this->invoice->service()->clearPartial()->setDueDate()->setStatus(Invoice::STATUS_PARTIAL)->updateBalance($amount_paid);

            } 
            elseif ($this->invoice->partial > 0 && $this->invoice->partial > $this->payment_amount) 
            { 
                //partial amount exists, but the amount is less than the partial amount
            
                $amount_paid = $this->payment_amount * -1;

                $this->invoice->service()->updatePartial($amount_paid)->updateBalance($amount_paid);
            
            } 
            elseif ($this->invoice->partial > 0 && $this->invoice->partial < $this->payment_amount) 
            { 
                //partial exists and the amount paid is GREATER than the partial amount

                $amount_paid = $this->payment_amount * -1;
            
                $this->invoice->service()->clearPartial()->setDueDate()->setStatus(Invoice::STATUS_PARTIAL)->updateBalance($amount_paid);
            
            }

        } 
        else 
        {
            if ($this->payment_amount == $this->invoice->balance) 
            {   
                $amount_paid = $this->payment_amount * -1;

                $this->invoice->service()->clearPartial()->setStatus(Invoice::STATUS_PAID)->updateBalance($amount_paid);

            } 
            elseif ($this->payment_amount < $this->invoice->balance) 
            { 
                //partial invoice payment made

                $amount_paid = $this->payment_amount * -1;

                $this->invoice->service()->clearPartial()->setStatus(Invoice::STATUS_PARTIAL)->updateBalance($amount_paid);

            
            } 
            elseif ($this->payment_amount > $this->invoice->balance) 
            {   
                //partial invoice payment made

                $amount_paid = $this->invoice->balance * -1;
                
                $this->invoice->service()->clearPartial()->setStatus(Invoice::STATUS_PAID)->updateBalance($amount_paid);
            
            }
        }

        // $this->payment
        //      ->ledger()
        //      ->updatePaymentBalance($this->payment_amount * -1);


        $this->payment
             ->ledger()
             ->updatePaymentBalance($amount_paid);

        $this->invoice->client->service()->updateBalance($amount_paid)->save();

        /* Update Pivot Record amount */
        $this->payment->invoices->each(function ($inv) use($amount_paid){
            if ($inv->id == $this->invoice->id) {
                $inv->pivot->amount = ($amount_paid*-1);
                $inv->pivot->save();
            }
        });

        $this->invoice->service()->applyNumber()->save();

        return $this->invoice;
    }
}
