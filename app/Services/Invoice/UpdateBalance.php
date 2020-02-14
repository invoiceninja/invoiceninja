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

namespace App\Services\Invoice;

use App\Models\Invoice;

class UpdateBalance
{

    private $invoice;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }


  	public function run($balance_adjustment)
  	{

        if ($this->invoice->is_deleted) {
            return;
        }

        $balance_adjustment = floatval($balance_adjustment);

        $this->invoice->balance += $balance_adjustment;

        if ($this->invoice->balance == 0) {
            $this->status_id = Invoice::STATUS_PAID;
            // $this->save();
            // event(new InvoiceWasPaid($this, $this->company));

        }

        return $this->invoice;
  	}
}
