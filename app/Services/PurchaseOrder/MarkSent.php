<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\PurchaseOrder;

use App\Models\Vendor;
use App\Models\Webhook;
use App\Models\PurchaseOrder;

class MarkSent
{
    public function __construct(public Vendor $vendor, public PurchaseOrder $purchase_order)
    {
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
            ->adjustBalance($this->purchase_order->amount) //why was this commented out previously?
            ->save();

        $this->purchase_order->sendEvent(Webhook::EVENT_SENT_PURCHASE_ORDER, "vendor");

        return $this->purchase_order;
    }
}
