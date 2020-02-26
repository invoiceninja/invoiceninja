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

class InvoiceRepository extends BaseRepository {
	use MakesHash;

	/**
	 * Gets the class name.
	 *
	 * @return     string  The class name.
	 */
	public function getClassName() {
		return Invoice::class ;
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
	public function markSent(Invoice $invoice):?Invoice {
		return $invoice->service()->markSent()->save();
	}
}
