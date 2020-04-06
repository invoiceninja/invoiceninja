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
    public function invoiceDeletable() :bool
    {

    	if($this->invoice->status_id <= 2 && $this->invoice->is_deleted == false && $this->invoice->deleted_at == NULL)
    		return true;

    	return false;
    }

    public function invoiceCancellable() :bool
    {

		if($this->invoice->status_id == 3 && $this->invoice->is_deleted == false && $this->invoice->deleted_at == NULL)
			return true;

		return false;
    }

    public function invoiceReversable() :bool
    {

		if(($this->invoice->status_id == 3 || $this->invoice->status_id == 4) && $this->invoice->is_deleted == false && $this->invoice->deleted_at == NULL)
			return true;

		return false;
    }
}
