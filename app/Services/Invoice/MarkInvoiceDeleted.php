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

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;

class MarkInvoiceDeleted extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    private $adjustment_amount = 0;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {
        if($this->invoice->is_deleted)
            return $this->invoice;

        // if(in_array($this->invoice->status_id, ['currencies', 'industries', 'languages', 'countries', 'banks']))
        //     return $this->
             
        $this->cleanup()
             ->setAdjustmentAmount()
             ->deletePaymentables();
    }


    private function setAdjustmentAmount()
    {

        foreach ($this->invoice->payments as $payment) { 
            $this->adjustment_amount += $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum(DB::raw('amount'));
        }

        return $this;
    }

    private function cleanup()
    {

        $check = false;
        
        $x=0;

        do {
            $number = $this->calcNumber($x);
            $check = $this->checkNumberAvailable(Invoice::class, $this->invoice, $number);
            $x++;
        } while (!$check);

        $this->invoice->number = $number;

        //wipe references to invoices from related entities.
        $this->invoice->tasks()->update(['invoice_id' => null]);
        $this->invoice->expenses()->update(['invoice_id' => null]);

        return $this;
    
    }

    private function calcNumber($x)
    {
        if ($x==0) {
            $number = $this->invoice->number . '_' . ctrans('texts.deleted');
        } else {
            $number = $this->invoice->number . '_' . ctrans('texts.deleted') . '_'. $x;
        }

        return $number;
    }


    private function deletePaymentables()
    {

        $this->invoice->payments->each(function ($payment){
            $payment->paymentables()
                    ->where('paymentable_type', '=', 'invoices')
                    ->where('paymentable_id', $this->invoice->id)
                    ->update(['deleted_at' => now()]);
        });

        
        return $this;
    }
}
