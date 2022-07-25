<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Models\Client;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class ApplyRecurringNumber extends AbstractService
{
    use GeneratesCounter;

    private $client;

    private $invoice;

    private bool $completed = true;

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
                $this->trySaving();
                //$this->invoice->number = $this->getNextRecurringInvoiceNumber($this->client, $this->invoice);
                break;
            case 'when_sent':
                if ($this->invoice->status_id == Invoice::STATUS_SENT) {
                    $this->trySaving();
                    // $this->invoice->number = $this->getNextRecurringInvoiceNumber($this->client, $this->invoice);
                }
                break;

            default:
                // code...
                break;
        }

        return $this->invoice;
    }

    private function trySaving()
    {
        $x = 1;

        do {
            try {
                $this->invoice->number = $this->getNextRecurringInvoiceNumber($this->client, $this->invoice);
                $this->invoice->saveQuietly();

                $this->completed = false;
            } catch (QueryException $e) {
                $x++;

                if ($x > 10) {
                    $this->completed = false;
                }
            }
        } while ($this->completed);
    }
}
