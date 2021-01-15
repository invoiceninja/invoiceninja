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

namespace App\Repositories;

use App\Helpers\Invoice\InvoiceSum;
use App\Models\RecurringInvoice;

/**
 * RecurringInvoiceRepository.
 */
class RecurringInvoiceRepository extends BaseRepository
{
    public function save($data, RecurringInvoice $invoice) : ?RecurringInvoice
    {

        $invoice = $this->alternativeSave($data, $invoice);
        // $invoice->fill($data);

        // $invoice->save();

        // $invoice_calc = new InvoiceSum($invoice);

        // $invoice->service()
        //         ->applyNumber()
        //         ->createInvitations()
        //         ->save();
        
        // $invoice = $invoice_calc->build()->getRecurringInvoice();

        return $invoice;
    }
}
