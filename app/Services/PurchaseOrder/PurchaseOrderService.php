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


use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;

class PurchaseOrderService
{
    use MakesHash;

    public PurchaseOrder $purchase_order;

    public function __construct($purchase_order)
    {
        $this->purchase_order = $purchase_order;
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

    public function fillDefaults()
    {
        $settings = $this->purchase_order->client->getMergedSettings();

        //TODO implement design, footer, terms


        /* If client currency differs from the company default currency, then insert the client exchange rate on the model.*/
        if (!isset($this->purchase_order->exchange_rate) && $this->purchase_order->client->currency()->id != (int)$this->purchase_order->company->settings->currency_id)
            $this->purchase_order->exchange_rate = $this->purchase_order->client->currency()->exchange_rate;

        if (!isset($this->purchase_order->public_notes))
            $this->purchase_order->public_notes = $this->purchase_order->client->public_notes;


        return $this;
    }
}
