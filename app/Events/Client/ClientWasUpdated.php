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

namespace App\Events\Client;

use App\Models\Client;
use Illuminate\Queue\SerializesModels;

/**
 * Class ClientWasUpdated.
 */
class ClientWasUpdated
{
    use SerializesModels;

    /**
     * @var Client
     */
    public $client;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Client $client
     */
    public function __construct(Client $client, Company $company, array $event_vars)
    {
        $this->client = $client;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
