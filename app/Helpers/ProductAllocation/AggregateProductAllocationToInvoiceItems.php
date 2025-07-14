<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\ProductAllocation;

use App\DataMapper\InvoiceItem;
use App\Models\Invoice;

class AggregateProductAllocationToInvoiceItems
{

    /**
     * Summary of __construct
     * @param \App\Models\Invoice $invoice
     * @param \App\Models\ProductAllocation[] $product_allocations
     */
    public function __construct(protected Invoice $invoice, private array $product_allocations)
    {
    }

    public function aggregate()
    {
        /*Don't double pay*/
        if ($this->invoice->status_id != Invoice::STATUS_DRAFT) {
            throw new \Exception('Invoice is not in draft status.');
        }

        // filter to only use unapplied product allocations
        $invoice = $this->invoice;
        $items = array_filter($this->product_allocations, function ($product_allocation) use ($invoice) {
            // Loop through $items and check if the 'id' exists in any item's 'product_allocation_ids'
            foreach ($invoice->line_items as $item) {
                if (in_array($product_allocation['id'], $item->product_allocation_ids)) {
                    return false; // Filter out this property
                }
            }
            return true; // Keep this property
        });

        // aggregate to reduce invoice rows
        /** @var \App\Models\ProductAllocation[] $aggregatedItems */
        $aggregatedItems = [];
        foreach ($items as $item) {
            // Create a unique key based on the properties you want to group by
            $key = $item->product->product_key . '|' . $item->invoice_aggregation_key;

            if (isset($aggregatedItems[$key])) {
                $aggregatedItems[$key]['quantity'] += $item->quantity;
                $aggregatedItems[$key]['product_allocation_ids'][] = $item->hashed_id;
                $aggregatedItems[$key]['public_notes'] = array_unique(
                    array_merge($aggregatedItems[$key]['public_notes'], [$item['public_notes']])
                );
            } else {
                $aggregatedItems[$key] = [
                    'quantity' => $item->quantity,
                    'product' => $item->product,
                    'invoice_aggregation_key' => $item->invoice_aggregation_key,
                    'custom_value1' => $item->custom_value1,
                    'custom_value2' => $item->custom_value2,
                    'custom_value3' => $item->custom_value3,
                    'custom_value4' => $item->custom_value4,
                    'product_allocation_ids' => [$item->hashed_id],
                    'public_notes' => [$item['public_notes']],
                ];
            }
        }
        $aggregatedItems = array_values($aggregatedItems);

        // apply to line_items
        $lineItems = $invoice->line_items;
        foreach ($aggregatedItems as $aggregatedItem) {
            // Try to find existing line item by product_key
            $existingIndex = null;

            foreach ($lineItems as $index => $existingItem) {
                if ($existingItem->product_key === $aggregatedItem['product']->product_key) {
                    $existingIndex = $index;
                    break;
                }
            }

            if (!is_null($existingIndex)) {
                // Append to existing line item
                $lineItems[$existingIndex]->quantity += $aggregatedItem['quantity'];
                $lineItems[$existingIndex]->line_total = round(
                    $lineItems[$existingIndex]->cost * $lineItems[$existingIndex]->quantity,
                    2
                );
                $lineItems[$existingIndex]->product_allocation_ids = array_merge(
                    $lineItems[$existingIndex]->product_allocation_ids ?? [],
                    $aggregatedItem['product_allocation_ids']
                );
                $lineItems[$existingIndex]->notes = implode("\n", array_unique(array_filter([
                    $lineItems[$existingIndex]->notes ?? '',
                    ...$aggregatedItem['public_notes']
                ])));
            } else {
                // Create a new line item
                $item = new InvoiceItem();
                $item->quantity = $aggregatedItem['quantity'];
                $item->cost = $aggregatedItem['product']->cost;
                $item->product_key = $aggregatedItem['product']->product_key;
                $item->line_total = round($item->cost * $item->quantity, 2);
                $item->notes = implode("\n", $aggregatedItem['public_notes']);
                $item->custom_value1 = $aggregatedItem['custom_value1'];
                $item->custom_value2 = $aggregatedItem['custom_value2'];
                $item->custom_value3 = $aggregatedItem['custom_value3'];
                $item->custom_value4 = $aggregatedItem['custom_value4'];
                $item->product_allocation_ids = $aggregatedItem['product_allocation_ids'];
                $item->type_id = '1';

                $lineItems[] = $item;
            }
        }
        $invoice->line_items = $lineItems;

        return $invoice;
    }
}
