<?php


namespace App\Services\PurchaseOrder;


use App\Events\PurchaseOrder\PurchaseOrderWasMarkedSent;
use App\Models\PurchaseOrder;
use App\Utils\Ninja;

class MarkSent
{
    private $client;

    private $purchase_order;

    public function __construct($client, $purchase_order)
    {
        $this->client = $client;
        $this->purchase_order = $purchase_order;
    }

    public function run()
    {

        /* Return immediately if status is not draft */
        if ($this->purchase_order->status_id != PurchaseOrder::STATUS_DRAFT) {
            return $this->purchase_order;
        }

        $this->purchase_order->markInvitationsSent();

        $this->purchase_order
            ->service()
            ->setStatus(PurchaseOrder::STATUS_SENT)
            ->applyNumber()
            //  ->adjustBalance($this->purchase_order->amount)
            //  ->touchPdf()
            ->save();

        event(new PurchaseOrderWasMarkedSent($this->purchase_order, $this->purchase_order->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->purchase_order;
    }
}
