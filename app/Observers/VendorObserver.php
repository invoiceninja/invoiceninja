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
    public $afterCommit = true;

    /**
     * Handle the vendor "created" event.
     *
     * @param Vendor $vendor
     * @return void
     */
    public function created(Vendor $vendor)
    {
        $subscriptions = Webhook::where('company_id', $vendor->company_id)
                                    ->where('event_id', Webhook::EVENT_CREATE_VENDOR)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_VENDOR, $vendor, $vendor->company)->delay(0);
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
        $event = Webhook::EVENT_UPDATE_VENDOR;

        if ($vendor->getOriginal('deleted_at') && !$vendor->deleted_at) {
            $event = Webhook::EVENT_RESTORE_VENDOR;
        }

        if ($vendor->is_deleted) {
            $event = Webhook::EVENT_DELETE_VENDOR;
        }


        $subscriptions = Webhook::where('company_id', $vendor->company_id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $vendor, $vendor->company)->delay(0);
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
        if ($vendor->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $vendor->company_id)
                                    ->where('event_id', Webhook::EVENT_ARCHIVE_VENDOR)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_VENDOR, $vendor, $vendor->company)->delay(0);
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
