<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\Models\Subscription;

class SubscriptionFactory
{
    public static function create(int $company_id, int $user_id) :Subscription
    {
        $subscription = new Subscription;
        $subscription->company_id = $company_id;
        $subscription->user_id = $user_id;
        $subscription->target_url = '';
        $subscription->event_id = 1;
        $subscription->format = 'JSON';

        return $subscription;
    }
}
