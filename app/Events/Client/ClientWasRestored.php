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

namespace App\Events\Client;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Queue\SerializesModels;

/**
 * Class ClientWasRestored.
 */
class ClientWasRestored
{
    use SerializesModels;

    /**
     * @var Client
     */
    public $client;

    public $fromDeleted;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Client $client
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Client $client, $fromDeleted, Company $company, array $event_vars)
    {
        $this->client = $client;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
