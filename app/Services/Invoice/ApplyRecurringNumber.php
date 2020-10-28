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

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;

class ApplyRecurringNumber extends AbstractService
{
    use GeneratesCounter;

    private $client;

    private $invoice;

    public function __construct(Client $client, Invoice $invoice)
    {
        $this->client = $client;

        $this->invoice = $invoice;
    }

    public function run()
    {
        if ($this->invoice->number != '') {
            return $this->invoice;
        }

        switch ($this->client->getSetting('counter_number_applied')) {
            case 'when_saved':
                $this->invoice->number = $this->getNextRecurringInvoiceNumber($this->client);
                break;
            case 'when_sent':
                if ($this->invoice->status_id == Invoice::STATUS_SENT) {
                $this->invoice->number = $this->getNextRecurringInvoiceNumber($this->client);
                }
                break;

            default:
                // code...
                break;
        }

        return $this->invoice;
    }
}
