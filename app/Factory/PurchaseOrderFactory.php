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

namespace App\Factory;

use App\Models\Client;
use App\Models\PurchaseOrder;

class PurchaseOrderFactory
{
    public static function create(int $company_id, int $user_id, object $settings = null, Client $client = null): PurchaseOrder
    {
        $purchase_order = new PurchaseOrder();
        $purchase_order->status_id = PurchaseOrder::STATUS_DRAFT;
        $purchase_order->number = null;
        $purchase_order->discount = 0;
        $purchase_order->is_amount_discount = true;
        $purchase_order->po_number = '';
        $purchase_order->footer = '';
        $purchase_order->terms = '';
        $purchase_order->public_notes = '';
        $purchase_order->private_notes = '';
        $purchase_order->date = now()->format('Y-m-d');
        $purchase_order->due_date = null;
        $purchase_order->partial_due_date = null;
        $purchase_order->is_deleted = false;
        $purchase_order->line_items = json_encode([]);
        $purchase_order->tax_name1 = '';
        $purchase_order->tax_rate1 = 0;
        $purchase_order->tax_name2 = '';
        $purchase_order->tax_rate2 = 0;
        $purchase_order->tax_name3 = '';
        $purchase_order->tax_rate3 = 0;
        $purchase_order->custom_value1 = '';
        $purchase_order->custom_value2 = '';
        $purchase_order->custom_value3 = '';
        $purchase_order->custom_value4 = '';
        $purchase_order->amount = 0;
        $purchase_order->balance = 0;
        $purchase_order->partial = 0;
        $purchase_order->user_id = $user_id;
        $purchase_order->company_id = $company_id;
        $purchase_order->recurring_id = null;
        $purchase_order->exchange_rate = 1;
        $purchase_order->total_taxes = 0;

        return $purchase_order;
    }
}
