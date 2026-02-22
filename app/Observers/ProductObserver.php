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

namespace App\Observers;

use App\Jobs\Util\WebhookHandler;
use App\Models\Product;
use App\Models\Webhook;

class ProductObserver
{
    public $afterCommit = true;

    /**
     * Handle the product "created" event.
     *
     * @param Product $product
     * @return void
     */
    public function created(Product $product)
    {
        $subscriptions = Webhook::where('company_id', $product->company_id)
            ->where('event_id', Webhook::EVENT_CREATE_PRODUCT)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_PRODUCT, $product, $product->company)->delay(0);
        }

        // Only push to QuickBooks if:
        // 1. QuickBooks is connected
        // 2. Product sync is enabled
        // 3. We're NOT currently importing from QuickBooks (prevent circular sync)
        if ($product->company->quickbooks
            && $product->company->shouldPushToQuickbooks('product')
            && empty(\App\Services\Quickbooks\QuickbooksService::$importing[$product->company_id])) {

            \App\Jobs\Quickbooks\PushToQuickbooks::dispatch(
                'product',
                $product->id,
                $product->company->db
            );

        }
    }

    /**
     * Handle the product "updated" event.
     *
     * @param Product $product
     * @return void
     */
    public function updated(Product $product)
    {
        $event = Webhook::EVENT_UPDATE_PRODUCT;

        if ($product->getOriginal('deleted_at') && !$product->deleted_at) {
            $event = Webhook::EVENT_RESTORE_PRODUCT;
        }

        if ($product->is_deleted) {
            $event = Webhook::EVENT_DELETE_PRODUCT;
        }


        $subscriptions = Webhook::where('company_id', $product->company_id)
            ->where('event_id', $event)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $product, $product->company)->delay(0);
        }


        nlog("product updated observer");
    }

    /**
     * Handle the product "deleted" event.
     *
     * @param Product $product
     * @return void
     */
    public function deleted(Product $product)
    {
        if ($product->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $product->company_id)
            ->where('event_id', Webhook::EVENT_ARCHIVE_PRODUCT)
            ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_PRODUCT, $product, $product->company)->delay(0);
        }
    }

    /**
     * Handle the product "restored" event.
     *
     * @param Product $product
     * @return void
     */
    public function restored(Product $product)
    {
        //
    }

    /**
     * Handle the product "force deleted" event.
     *
     * @param Product $product
     * @return void
     */
    public function forceDeleted(Product $product)
    {
        //
    }
}
