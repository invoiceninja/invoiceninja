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

use App\Events\Invoice\InvoiceWasCancelled;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Ninja;
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

        return $this->invoice;
    }


    private function calcNumber($x)
    {
    	if($x==0)
			$number = $this->invoice->number . '_' . ctrans('texts.deleted');
		else
			$number = $this->invoice->number . '_' . ctrans('texts.deleted') . '_'. $x;

		return $number;

    }

}
