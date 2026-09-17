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

use App\Models\Invoice;
use App\Models\PurchaseOrder;

class CloneInvoiceToPurchaseOrderFactory
{
    public static function create(Invoice $invoice, $user_id): ?PurchaseOrder
    {

        $line_items = collect($invoice->line_items)->map(function ($item) {
            $item->cost = $item->product_cost;

            return $item;
        })->all();

        $purchase_order = new PurchaseOrder();
        $purchase_order->invoice_id = $invoice->id;
        $purchase_order->client_id = $invoice->client_id;
        $purchase_order->user_id = $user_id;
        $purchase_order->company_id = $invoice->company_id;
        $purchase_order->assigned_user_id = $invoice->assigned_user_id;
        $purchase_order->discount = $invoice->discount;
        $purchase_order->is_amount_discount = $invoice->is_amount_discount;
        $purchase_order->po_number = $invoice->po_number;
        $purchase_order->is_deleted = false;
        $purchase_order->footer = '';
        $purchase_order->public_notes = '';
        $purchase_order->private_notes = $invoice->private_notes;
        $purchase_order->terms = '';
        $purchase_order->tax_name1 = $invoice->tax_name1;
        $purchase_order->tax_rate1 = $invoice->tax_rate1;
        $purchase_order->tax_name2 = $invoice->tax_name2;
        $purchase_order->tax_rate2 = $invoice->tax_rate2;
        $purchase_order->tax_name3 = $invoice->tax_name3;
        $purchase_order->tax_rate3 = $invoice->tax_rate3;
        $purchase_order->total_taxes = $invoice->total_taxes;
        $purchase_order->uses_inclusive_taxes = $invoice->uses_inclusive_taxes;
        $purchase_order->custom_value1 = $invoice->custom_value1;
        $purchase_order->custom_value2 = $invoice->custom_value2;
        $purchase_order->custom_value3 = $invoice->custom_value3;
        $purchase_order->custom_value4 = $invoice->custom_value4;
        $purchase_order->amount = $invoice->amount;
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
