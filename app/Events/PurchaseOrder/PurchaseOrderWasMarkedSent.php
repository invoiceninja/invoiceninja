<?php


namespace App\Events\PurchaseOrder;

use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Queue\SerializesModels;

/**
 * Class PurchaseOrderWasMarkedSent.
 */
class PurchaseOrderWasMarkedSent
{
    use SerializesModels;

    /**
     * @var \App\Models\PurchaseOrder
     */
    public $purchase_order;

    public $company;

    public $event_vars;
    /**
     * Create a new event instance.
     *
     * @param PurchaseOrder $purchase_order
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(PurchaseOrder $purchase_order, Company $company, array $event_vars)
    {
        $this->purchase_order = $purchase_order;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
