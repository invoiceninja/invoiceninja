<?php

namespace App\Repositories;

use App\Helpers\Invoice\InvoiceCalc;
use App\Models\Invoice;
use Illuminate\Http\Request;

/**
 * InvoiceRepository
 */
class InvoiceRepository extends BaseRepository
{


    /**
     * Gets the class name.
     *
     * @return     ::class  The class name.
     */
    public function getClassName()
    {
        return Invoice::class;
    }

    
	/**
     * Saves the invoices
     *
     * @param      array.                                        $data     The invoice data
     * @param      InvoiceCalc|\App\Models\Invoice               $invoice  The invoice
     *
     * @return     Invoice|InvoiceCalc|\App\Models\Invoice|null  Returns the invoice object
     */
    public function save($data, Invoice $invoice) : ?Invoice
	{

        $invoice->fill($data);
        
        $invoice->save();

        $invoice_calc = new InvoiceCalc($invoice, $invoice->settings);

        $invoice = $invoice_calc->build()->getInvoice();
        
        $invoice->save();

        return $invoice;

	}


    /**
     * Mark the invoice as sent.
     *
     * @param      \App\Models\Invoice               $invoice  The invoice
     *
     * @return     Invoice|\App\Models\Invoice|null  Return the invoice object
     */
    public function markSent(Invoice $invoice) : ?Invoice
    {

        if($invoice->status_id >= Invoice::STATUS_SENT)
            return $invoice;

        $invoice->status_id = Invoice::STATUS_SENT;
        
        $invoice->save();

        return $invoice;

    }

}