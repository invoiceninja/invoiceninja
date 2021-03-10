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

namespace App\Observers;

use App\Models\ClientSubscription;

class ClientSubscriptionObserver
{
    /**
     * Handle the client_subscription "created" event.
     *
     * @param ClientSubscription $client_subscription
     * @return void
     */
    public function created(ClientSubscription $client_subscription)
    {
        //
    }

    /**
     * Handle the client_subscription "updated" event.
     *
     * @param ClientSubscription $client_subscription
     * @return void
     */
    public function updated(ClientSubscription $client_subscription)
    {
        //
    }

    /**
     * Handle the client_subscription "deleted" event.
     *
     * @param ClientSubscription $client_subscription
     * @return void
     */
    public function deleted(ClientSubscription $client_subscription)
    {
        //
    }

    /**
     * Handle the client_subscription "restored" event.
     *
     * @param ClientSubscription $client_subscription
     * @return void
     */
    public function restored(ClientSubscription $client_subscription)
    {
        //
    }

    /**
     * Handle the client_subscription "force deleted" event.
     *
     * @param ClientSubscription $client_subscription
     * @return void
     */
    public function forceDeleted(ClientSubscription $client_subscription)
    {
        //
    }
}
