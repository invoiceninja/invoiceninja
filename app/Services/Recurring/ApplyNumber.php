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

namespace App\Services\Recurring;

use App\Models\Client;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class ApplyNumber extends AbstractService
{
    use GeneratesCounter;

    private $client;

    private $recurring_entity;

    private bool $completed = true;

    public function __construct(Client $client, $recurring_entity)
    {
        $this->client = $client;

        $this->recurring_entity = $recurring_entity;
    }

    /* Recurring numbers are set when saved */
    public function run()
    {
        if ($this->recurring_entity->number != '') {
            return $this->recurring_entity;
        }

        $this->trySaving();
        //$this->recurring_entity->number = $this->getNextRecurringInvoiceNumber($this->client, $this->recurring_entity);

        return $this->recurring_entity;
    }

    private function trySaving()
    {
        $x = 1;

        do {
            try {
                $this->recurring_entity->number = $this->getNextRecurringInvoiceNumber($this->client, $this->recurring_entity);
                $this->recurring_entity->saveQuietly();

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
