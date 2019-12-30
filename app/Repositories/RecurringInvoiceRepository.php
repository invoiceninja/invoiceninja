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

use App\Helpers\Invoice\InvoiceSum;
use App\Models\RecurringInvoice;
use Illuminate\Http\Request;

/**
 * RecurringInvoiceRepository
 */
class RecurringInvoiceRepository extends BaseRepository
{
    public function getClassName()
    {
        return RecurringInvoice::class;
    }
    
    public function save($data, RecurringInvoice $invoice) : ?RecurringInvoice
    {
        $invoice->fill($data);
        
        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice, $invoice->settings);

        $invoice = $invoice_calc->build()->getInvoice();

        //fire events here that cascading from the saving of an invoice
        //ie. client balance update...
        
        return $invoice;
    }
}
