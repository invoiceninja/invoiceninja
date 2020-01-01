<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

class InvoiceItemTransformer extends EntityTransformer
{
    public function transform($item)
    {
        return [
            'id' => (int) $item->id,
            'product_key' => $item->product_key,
            'updated_at' => $item->updated_at,
            'archived_at' => $item->deleted_at,
            'notes' => $item->notes ?: '',
            'cost' => (float) $item->cost ?: '',
            'quantity' => (float) ($item->quantity ?: 0.0),
            'tax_name1' => $item->tax_name1 ? $item->tax_name1 : '',
            'tax_rate1' => (float) ($item->tax_rate1 ?: 0.0),
            'tax_name2' => $item->tax_name2 ? $item->tax_name2 : '',
            'tax_rate2' => (float) ($item->tax_rate2 ?: 0.0),
            'line_item_type_id' => (string) $item->line_item_type_id ?: '',
            'custom_value1' => $item->custom_value1 ?: '',
            'custom_value2' => $item->custom_value2 ?: '',
            'discount' => (float) $item->discount ?: '',
        ];
    }
}
