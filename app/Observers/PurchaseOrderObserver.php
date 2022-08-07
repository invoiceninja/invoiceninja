<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Observers;

use App\Models\PurchaseOrder;

class PurchaseOrderObserver
{
    /**
     * Handle the client "created" event.
     *
     * @param PurchaseOrder $purchase_order
     * @return void
     */
    public function created(PurchaseOrder $purchase_order)
    {
    }

    /**
     * Handle the client "updated" event.
     *
     * @param PurchaseOrder $purchase_order
     * @return void
     */
    public function updated(PurchaseOrder $purchase_order)
    {
    }

    /**
     * Handle the client "deleted" event.
     *
     * @param PurchaseOrder $purchase_order
     * @return void
     */
    public function deleted(PurchaseOrder $purchase_order)
    {
    }

    /**
     * Handle the client "restored" event.
     *
     * @param PurchaseOrder $purchase_order
     * @return void
     */
    public function restored(PurchaseOrder $purchase_order)
    {
        //
    }

    /**
     * Handle the client "force deleted" event.
     *
     * @param PurchaseOrder $purchase_order
     * @return void
     */
    public function forceDeleted(PurchaseOrder $purchase_order)
    {
        //
    }
}
