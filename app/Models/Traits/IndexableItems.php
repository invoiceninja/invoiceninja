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

namespace App\Models\Traits;

trait IndexableItems
{

    public function indexLineItems(): array
    {
        // Properly cast line items to ensure correct types
        $line_items = [];

        if ($this->line_items && is_array($this->line_items)) {
            foreach ($this->line_items as $item) {
                $line_items[] = [
                    'quantity' => (float) ($item->quantity ?? 0),
                    'net_cost' => (float) ($item->net_cost ?? 0),
                    'cost' => (float) ($item->cost ?? 0),
                    'product_key' => (string) ($item->product_key ?? ''),
                    'product_cost' => (float) ($item->product_cost ?? 0),
                    'notes' => (string) ($item->notes ?? ''),
                    'discount' => (float) ($item->discount ?? 0),
                    'is_amount_discount' => (bool) ($item->is_amount_discount ?? false),
                    'tax_name1' => (string) ($item->tax_name1 ?? ''),
                    'tax_rate1' => (float) ($item->tax_rate1 ?? 0),
                    'tax_name2' => (string) ($item->tax_name2 ?? ''),
                    'tax_rate2' => (float) ($item->tax_rate2 ?? 0),
                    'tax_name3' => (string) ($item->tax_name3 ?? ''),
                    'tax_rate3' => (float) ($item->tax_rate3 ?? 0),
                    'sort_id' => (string) ($item->sort_id ?? ''),
                    'line_total' => (float) ($item->line_total ?? 0),
                    'gross_line_total' => (float) ($item->gross_line_total ?? 0),
                    'tax_amount' => (float) ($item->tax_amount ?? 0),
                    'date' => (string) ($item->date ?? ''),
                    'custom_value1' => (string) ($item->custom_value1 ?? ''),
                    'custom_value2' => (string) ($item->custom_value2 ?? ''),
                    'custom_value3' => (string) ($item->custom_value3 ?? ''),
                    'custom_value4' => (string) ($item->custom_value4 ?? ''),
                    'type_id' => (string) ($item->type_id ?? ''),
                    'tax_id' => (string) ($item->tax_id ?? ''),
                    'task_id' => (string) ($item->task_id ?? ''),
                    'expense_id' => (string) ($item->expense_id ?? ''),
                    'unit_code' => (string) ($item->unit_code ?? ''),
                ];
            }
        }

        return $line_items;
    }

}