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


use App\Models\BillingSubscription;

class BillingSubscriptionRepository extends BaseRepository
{
    public function save($data, BillingSubscription $billing_subscription): ?BillingSubscription
    {
        $billing_subscription
            ->fill($data)
            ->save();

        return $billing_subscription;
    }
}
