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

use App\Factory\ExpenseFactory;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Admin\InventoryNotificationObject;
use App\Models\Product;
use App\Models\PurchaseOrder;

class PurchaseOrderInventory
{

    private PurchaseOrder $purchase_order;

    public function __construct(PurchaseOrder $purchase_order)
    {
        $this->purchase_order = $purchase_order;
    }

    public function run()
    {

        $line_items = $this->purchase_order->line_items;

        foreach($line_items as $item)
        {

            $p = Product::where('product_key', $item->product_key)->where('company_id', $this->purchase_order->company_id)->first();

            if(!$p)
                continue;

            $p->in_stock_quantity += $item->quantity;
            $p->saveQuietly();

        }

        $this->purchase_order->status_id = PurchaseOrder::STATUS_RECEIVED;
        $this->purchase_order->save();

        return $this->purchase_order;

    }

}
