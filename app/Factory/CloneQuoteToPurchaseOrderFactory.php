<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\PurchaseOrder;
use App\Models\Quote;

class CloneQuoteToPurchaseOrderFactory
{
    public static function create(Quote $quote, $user_id): ?PurchaseOrder
    {

        $line_items = collect($quote->line_items)->map(function ($item) {
            $item->cost = $item->product_cost;

            return $item;
        })->all();

        $purchase_order = new PurchaseOrder();
        $purchase_order->quote_id = $quote->id;
        $purchase_order->client_id = $quote->client_id;
        $purchase_order->user_id = $user_id;
        $purchase_order->company_id = $quote->company_id;
        $purchase_order->assigned_user_id = $quote->assigned_user_id;
        $purchase_order->discount = $quote->discount;
        $purchase_order->is_amount_discount = $quote->is_amount_discount;
        $purchase_order->po_number = $quote->po_number;
        $purchase_order->is_deleted = false;
        $purchase_order->footer = '';
        $purchase_order->public_notes = '';
        $purchase_order->private_notes = $quote->private_notes;
        $purchase_order->terms = '';
        $purchase_order->tax_name1 = $quote->tax_name1;
        $purchase_order->tax_rate1 = $quote->tax_rate1;
        $purchase_order->tax_name2 = $quote->tax_name2;
        $purchase_order->tax_rate2 = $quote->tax_rate2;
        $purchase_order->tax_name3 = $quote->tax_name3;
        $purchase_order->tax_rate3 = $quote->tax_rate3;
        $purchase_order->total_taxes = $quote->total_taxes;
        $purchase_order->uses_inclusive_taxes = $quote->uses_inclusive_taxes;
        $purchase_order->custom_value1 = $quote->custom_value1;
        $purchase_order->custom_value2 = $quote->custom_value2;
        $purchase_order->custom_value3 = $quote->custom_value3;
        $purchase_order->custom_value4 = $quote->custom_value4;
        $purchase_order->amount = $quote->amount;
        $purchase_order->balance = 0;
        $purchase_order->partial = 0;
        $purchase_order->exchange_rate = 1;
        $purchase_order->paid_to_date = 0;

        $purchase_order->status_id = PurchaseOrder::STATUS_DRAFT;
        $purchase_order->number = '';
        $purchase_order->date = null;
        $purchase_order->due_date = null;
        $purchase_order->partial_due_date = null;
        $purchase_order->line_items = $line_items;

        return $purchase_order;
    }
}
