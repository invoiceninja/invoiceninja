<?php

namespace App\Ninja\Transformers;

use App\Models\InvoiceItem;

class InvoiceItemTransformer extends EntityTransformer
{
	 /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="product_key", type="string", example="Item")
    * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
    * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
    * @SWG\Property(property="notes", type="string", example="Notes")
    * @SWG\Property(property="cost", type="number", format="float", example=10.00)
    * @SWG\Property(property="qty", type="number", format="float", example=1)
    * @SWG\Property(property="tax_name1", type="string", example="VAT")
    * @SWG\Property(property="tax_name2", type="string", example="Upkeep")
    * @SWG\Property(property="tax_rate1", type="number", format="float", example="17.5")
    * @SWG\Property(property="tax_rate2", type="number", format="float", example="30.0")
    * @SWG\Property(property="invoice_item_type_id", type="integer", example=1)
    * @SWG\Property(property="custom_value1", type="string", example="Value")
    * @SWG\Property(property="custom_value2", type="string", example="Value")
    * @SWG\Property(property="discount", type="number", format="float", example=10)
    */
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
