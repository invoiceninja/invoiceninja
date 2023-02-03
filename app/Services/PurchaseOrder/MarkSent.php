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

namespace App\Services\PurchaseOrder;

use App\Jobs\Util\WebhookHandler;
use App\Models\PurchaseOrder;
use App\Models\Webhook;
use App\Utils\Ninja;

class MarkSent
{
    private $vendor;

    private $purchase_order;

    public function __construct($vendor, $purchase_order)
    {
        $this->vendor = $vendor;
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
            ->adjustBalance($this->purchase_order->amount) //why was this commented out previously?
            ->save();

        $subscriptions = Webhook::where('company_id', $this->purchase_order->company_id)
            ->where('event_id', Webhook::EVENT_SENT_PURCHASE_ORDER)
            ->exists();

        if ($subscriptions)
            WebhookHandler::dispatch(Webhook::EVENT_SENT_PURCHASE_ORDER, $this->purchase_order, $this->purchase_order->company, 'vendor')->delay(0);

        return $this->purchase_order;
    }
}
