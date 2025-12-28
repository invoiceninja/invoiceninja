<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Client;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Queue\SerializesModels;

/**
 * Class ClientWasMerged.
 */
class ClientWasMerged
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $mergeable_client
     * @param Client $client
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(public string $mergeable_client, public Client $client, public Company $company, public array $event_vars)
    {
    }
}
