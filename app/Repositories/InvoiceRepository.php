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

namespace App\Repositories;

use App\Events\Invoice\InvoiceWasDeleted;
use App\Factory\InvoiceInvitationFactory;
use App\Jobs\Product\UpdateOrCreateProduct;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\MakesHash;

/**
 * InvoiceRepository
 */

class InvoiceRepository extends BaseRepository
{
    use MakesHash;

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
     * @param      InvoiceSum|\App\Models\Invoice               $invoice  The invoice
     *
     * @return     Invoice|InvoiceSum|\App\Models\Invoice|null  Returns the invoice object
     */
    public function save($data, Invoice $invoice):?Invoice
    {
        return $this->alternativeSave($data, $invoice);
    }

    /**
     * Mark the invoice as sent.
     *
     * @param      \App\Models\Invoice               $invoice  The invoice
     *
     * @return     Invoice|\App\Models\Invoice|null  Return the invoice object
     */
    public function markSent(Invoice $invoice):?Invoice
    {
        return $invoice->service()->markSent()->save();
    }

    public function getInvitationByKey($key) :?InvoiceInvitation
    {
        return InvoiceInvitation::whereRaw("BINARY `key`= ?", [$key])->first();
    }

    /**
     * Method is not protected, assumes that
     * other protections have been implemented prior
     * to hitting this method.
     *
     * ie. invoice can be deleted from a business logic perspective.
     *
     * @param  Invoice $invoice
     * @return Invoice $invoice
     */
    public function delete($invoice)
    {
        if ($invoice->is_deleted) {
            return;
        }

        $invoice->service()->handleCancellation()->save();
        
        $invoice = parent::delete($invoice);

        return $invoice;
    }

    public function reverse()
    {
    }

    public function cancel()
    {
    }
}
