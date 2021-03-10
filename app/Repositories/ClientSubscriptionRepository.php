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

namespace App\Repositories;


use App\Models\ClientSubscription;

class ClientSubscriptionRepository extends BaseRepository
{
    public function save($data, ClientSubscription $client_subscription): ?ClientSubscription
    {
        $client_subscription
            ->fill($data)
            ->save();

        return $client_subscription;
    }
}
