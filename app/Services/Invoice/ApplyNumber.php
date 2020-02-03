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

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;

class ApplyNumber
{
	use GeneratesCounter;

    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

  	public function __invoke($invoice)
  	{

        if ($invoice->number != '') 
            return $invoice;

        switch ($this->client->getSetting('counter_number_applied')) {
            case 'when_saved':
                $invoice->number = $this->getNextInvoiceNumber($this->client);
                break;
            case 'when_sent':
                if ($invoice->status_id == Invoice::STATUS_SENT) {
                    $invoice->number = $this->getNextInvoiceNumber($this->client);
                }
                break;
            
            default:
                # code...
                break;
        }
               
        return $invoice;
  	}
}