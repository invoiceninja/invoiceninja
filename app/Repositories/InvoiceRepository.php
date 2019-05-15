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

use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Helpers\Invoice\InvoiceCalc;
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
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
     * @return     string  The class name.
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

        /* Always carry forward the initial invoice amount this is important for tracking client balance changes later......*/
        $starting_amount = $invoice->amount;

        $invoice->fill($data);
        
        $invoice->save();

        $invoice_calc = new InvoiceCalc($invoice, $invoice->settings);

        $invoice = $invoice_calc->build()->getInvoice();
        
        $invoice->save();

        $finished_amount = $invoice->amount;

        if($finished_amount != $starting_amount)
            UpdateCompanyLedgerWithInvoice::dispatchNow($invoice);

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
        /* Return immediately if status is not draft*/
        if($invoice->status_id != Invoice::STATUS_DRAFT)
            return $invoice;

        $invoice->status_id = Invoice::STATUS_SENT;

        $invoice->save();

        return $invoice;

    }

}