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

use App\Jobs\Util\WebhookHandler;
use App\Models\Vendor;
use App\Models\Webhook;

class VendorObserver
{
    /**
     * Handle the vendor "created" event.
     *
     * @param Vendor $vendor
     * @return void
     */
    public function created(Vendor $vendor)
    {
        $subscriptions = Webhook::where('company_id', $vendor->company->id)
                                    ->where('event_id', Webhook::EVENT_CREATE_VENDOR)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_VENDOR, $vendor, $vendor->company)->delay(now()->addSeconds(rand(1,5)));
        }
    }

    /**
     * Handle the vendor "updated" event.
     *
     * @param Vendor $vendor
     * @return void
     */
    public function updated(Vendor $vendor)
    {
        $subscriptions = Webhook::where('company_id', $vendor->company->id)
                                    ->where('event_id', Webhook::EVENT_UPDATE_VENDOR)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_UPDATE_VENDOR, $vendor, $vendor->company)->delay(now()->addSeconds(rand(1,5)));
        }
    }

    /**
     * Handle the vendor "deleted" event.
     *
     * @param Vendor $vendor
     * @return void
     */
    public function deleted(Vendor $vendor)
    {
        $subscriptions = Webhook::where('company_id', $vendor->company->id)
                                    ->where('event_id', Webhook::EVENT_DELETE_VENDOR)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_DELETE_VENDOR, $vendor, $vendor->company)->delay(now()->addSeconds(rand(1,5)));
        }
    }

    /**
     * Handle the vendor "restored" event.
     *
     * @param Vendor $vendor
     * @return void
     */
    public function restored(Vendor $vendor)
    {
        //
    }

    /**
     * Handle the vendor "force deleted" event.
     *
     * @param Vendor $vendor
     * @return void
     */
    public function forceDeleted(Vendor $vendor)
    {
        //
    }

}
