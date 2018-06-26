<?php

namespace App\Ninja\Transformers;

use App\Models\InvoiceItem;

class InvoiceItemTransformer extends EntityTransformer
{
    public function transform(InvoiceItem $item)
    {
        return array_merge($this->getDefaults($item), [
            'id' => (int) $item->public_id,
            'product_key' => $item->product_key,
            'updated_at' => $this->getTimestamp($item->updated_at),
            'archived_at' => $this->getTimestamp($item->deleted_at),
            'notes' => $item->notes,
            'cost' => (float) $item->cost,
            'qty' => (float) ($item->qty ?: 0.0),
            'tax_name1' => $item->tax_name1 ? $item->tax_name1 : '',
            'tax_rate1' => (float) ($item->tax_rate1 ?: 0.0),
            'tax_name2' => $item->tax_name2 ? $item->tax_name2 : '',
            'tax_rate2' => (float) ($item->tax_rate2 ?: 0.0),
            'invoice_item_type_id' => (int) $item->invoice_item_type_id,
            'custom_value1' => $item->custom_value1 ?: '',
            'custom_value2' => $item->custom_value2 ?: '',
            'discount' => (float) $item->discount,
        ]);
    }
}
