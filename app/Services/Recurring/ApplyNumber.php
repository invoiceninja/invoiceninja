<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Recurring;

use App\Models\Client;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;

class ApplyNumber extends AbstractService
{
    use GeneratesCounter;

    private $client;

    private $recurring_entity;

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


        $this->recurring_entity->number = $this->getNextRecurringInvoiceNumber($this->client);


        // switch ($this->client->getSetting('counter_number_applied')) {
        //     case 'when_saved':
        //         $this->recurring_entity->number = $this->getNextRecurringInvoiceNumber($this->client);
        //         break;
        //     case 'when_sent':
        //         break;

        //     default:
        //         $this->recurring_entity->number = $this->getNextRecurringInvoiceNumber($this->client);
        //         break;
        // }

        return $this->recurring_entity;
    }
}
