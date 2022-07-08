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

namespace App\Services\PurchaseOrder;

use App\Jobs\Vendor\CreatePurchaseOrderPdf;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrder\ApplyNumber;
use App\Services\PurchaseOrder\CreateInvitations;
use App\Services\PurchaseOrder\GetPurchaseOrderPdf;
use App\Services\PurchaseOrder\PurchaseOrderExpense;
use App\Services\PurchaseOrder\TriggeredActions;
use App\Utils\Traits\MakesHash;

class PurchaseOrderService
{
    use MakesHash;

    public PurchaseOrder $purchase_order;

    public function __construct(PurchaseOrder $purchase_order)
    {
        $this->purchase_order = $purchase_order;
    }

    public function createInvitations()
    {

        $this->purchase_order = (new CreateInvitations($this->purchase_order))->run();

        return $this;
    }

    public function applyNumber()
    {
        $this->purchase_order = (new ApplyNumber($this->purchase_order->vendor, $this->purchase_order))->run();

        return $this;
    }

    public function fillDefaults()
    {
        // $settings = $this->purchase_order->client->getMergedSettings();

        // //TODO implement design, footer, terms

        // /* If client currency differs from the company default currency, then insert the client exchange rate on the model.*/
        // if (!isset($this->purchase_order->exchange_rate) && $this->purchase_order->client->currency()->id != (int)$this->purchase_order->company->settings->currency_id)
        //     $this->purchase_order->exchange_rate = $this->purchase_order->client->currency()->exchange_rate;

        // if (!isset($this->purchase_order->public_notes))
        //     $this->purchase_order->public_notes = $this->purchase_order->client->public_notes;


        return $this;
    }

    public function triggeredActions($request)
    {
        $this->purchase_order = (new TriggeredActions($this->purchase_order->load('invitations'), $request))->run();

        return $this;
    }

    public function getPurchaseOrderPdf($contact = null)
    {
        return (new GetPurchaseOrderPdf($this->purchase_order, $contact))->run();
    }

    public function setStatus($status)
    {
        $this->purchase_order->status_id = $status;

        return $this;
    }

    public function markSent()
    {
        $this->purchase_order = (new MarkSent($this->purchase_order->vendor, $this->purchase_order))->run();

        return $this;
    }

    public function touchPdf($force = false)
    {
        try {
        
            if($force){

                $this->purchase_order->invitations->each(function ($invitation) {
                    CreatePurchaseOrderPdf::dispatchNow($invitation);
                });

                return $this;
            }

            $this->purchase_order->invitations->each(function ($invitation) {
                CreatePurchaseOrderPdf::dispatch($invitation);
            });
        
        }
        catch(\Exception $e){

            nlog("failed creating purchase orders in Touch PDF");
        
        }

        return $this;
    }

    public function add_to_inventory()
    {
        if($this->purchase_order->status_id >= PurchaseOrder::STATUS_RECEIVED)
            return $this->purchase_order;

        $this->purchase_order = (new PurchaseOrderInventory($this->purchase_order))->run();

        return $this;
    }

    public function expense()
    {
        $this->markSent();
        
        if($this->purchase_order->expense()->exists())
            return $this;

        $expense = (new PurchaseOrderExpense($this->purchase_order))->run();

        return $expense;
    }

    /**
     * Saves the purchase order.
     * @return \App\Models\PurchaseOrder object
     */
    public function save(): ?PurchaseOrder
    {
        $this->purchase_order->saveQuietly();

        return $this->purchase_order;
    }

}
