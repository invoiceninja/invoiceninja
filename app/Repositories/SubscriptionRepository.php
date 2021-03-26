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


use App\Models\Subscription;

class SubscriptionRepository extends BaseRepository
{
    public function save($data, Subscription $subscription): ?Subscription
    {
        $subscription
            ->fill($data)
            ->save();

        return $subscription;
    }
}