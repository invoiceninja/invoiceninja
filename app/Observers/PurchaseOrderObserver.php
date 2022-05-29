<?php


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
