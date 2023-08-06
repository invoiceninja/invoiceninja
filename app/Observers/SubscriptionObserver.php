<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Models\Subscription;

class SubscriptionObserver
{
    /**
     * Handle the subscription "created" event.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function created(Subscription $subscription)
    {
        //
    }

    /**
     * Handle the subscription "updated" event.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function updated(Subscription $subscription)
    {
        //
    }

    /**
     * Handle the subscription "deleted" event.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function deleted(Subscription $subscription)
    {
        //
    }

    /**
     * Handle the subscription "restored" event.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function restored(Subscription $subscription)
    {
        //
    }

    /**
     * Handle the subscription "force deleted" event.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function forceDeleted(Subscription $subscription)
    {
        //
    }
}
