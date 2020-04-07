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

namespace App\Utils\Traits\Invoice;


trait ActionsInvoice
{
    public function invoiceDeletable($invoice) :bool
    {

    	if($invoice->status_id <= 2 && $invoice->is_deleted == false && $invoice->deleted_at == NULL)
    		return true;

    	return false;
    }

    public function invoiceCancellable($invoice) :bool
    {

		if($invoice->status_id == 3 && $invoice->is_deleted == false && $invoice->deleted_at == NULL)
			return true;

		return false;
    }

    public function invoiceReversable($invoice) :bool
    {

		if(($invoice->status_id == 3 || $invoice->status_id == 4) && $invoice->is_deleted == false && $invoice->deleted_at == NULL)
			return true;

		return false;
    }
}
