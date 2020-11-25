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

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
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

        return $this->invoice;
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
}
