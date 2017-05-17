<?php

namespace App\Ninja\Transformers;

use App\Models\Product;

/**
 * @SWG\Definition(definition="Product", @SWG\Xml(name="Product"))
 */
class ProductTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="product_key", type="string", example="Item")
     * @SWG\Property(property="notes", type="string", example="Notes...")
     * @SWG\Property(property="cost", type="number", format="float", example=10.00)
     * @SWG\Property(property="qty", type="number", format="float", example=1)
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     */
    public function transform(Product $product)
    {
        return array_merge($this->getDefaults($product), [
            'id' => (int) $product->public_id,
            'product_key' => $product->product_key,
            'notes' => $product->notes,
            'cost' => $product->cost,
            'qty' => $product->qty,
            'tax_name1' => $product->tax_name1 ?: '',
            'tax_rate1' => (float) $product->tax_rate1,
            'tax_name2' => $product->tax_name2 ?: '',
            'tax_rate2' => (float) $product->tax_rate2,
            'updated_at' => $this->getTimestamp($product->updated_at),
            'archived_at' => $this->getTimestamp($product->deleted_at),
        ]);
    }
}
