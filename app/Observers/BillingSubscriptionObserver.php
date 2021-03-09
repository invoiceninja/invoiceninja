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

use App\Models\BillingSubscription;

class BillingSubscriptionObserver
{
    /**
     * Handle the billing_subscription "created" event.
     *
     * @param BillingSubscription $billing_subscription
     * @return void
     */
    public function created(BillingSubscription $billing_subscription)
    {
        //
    }

    /**
     * Handle the billing_subscription "updated" event.
     *
     * @param BillingSubscription $billing_subscription
     * @return void
     */
    public function updated(BillingSubscription $billing_subscription)
    {
        //
    }

    /**
     * Handle the billing_subscription "deleted" event.
     *
     * @param BillingSubscription $billing_subscription
     * @return void
     */
    public function deleted(BillingSubscription $billing_subscription)
    {
        //
    }

    /**
     * Handle the billing_subscription "restored" event.
     *
     * @param BillingSubscription $billing_subscription
     * @return void
     */
    public function restored(BillingSubscription $billing_subscription)
    {
        //
    }

    /**
     * Handle the billing_subscription "force deleted" event.
     *
     * @param BillingSubscription $billing_subscription
     * @return void
     */
    public function forceDeleted(BillingSubscription $billing_subscription)
    {
        //
    }
}
