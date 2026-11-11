<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Client;

use App\Models\User;
use App\Models\Company;
use Illuminate\Queue\SerializesModels;

/**
 * Class ClientWasMerged.
 */
class ClientWasPurged
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $purged_client
     * @param User $user
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(public string $purged_client, public User $user, public Company $company, public array $event_vars) {}
}
