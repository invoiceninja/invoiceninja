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
     * @SWG\Property(property="cost", type="float", example=10.00)
     * @SWG\Property(property="qty", type="float", example=1)
     * @SWG\Property(property="default_tax_rate_id", type="integer", example=1)
     * @SWG\Property(property="updated_at", type="timestamp", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="timestamp", example=1451160233, readOnly=true)
     */
    public function transform(Product $product)
    {
        return array_merge($this->getDefaults($product), [
            'id' => (int) $product->public_id,
            'product_key' => $product->product_key,
            'notes' => $product->notes,
            'cost' => $product->cost,
            'qty' => $product->qty,
            'default_tax_rate_id' => $product->default_tax_rate_id,
            'updated_at' => $this->getTimestamp($product->updated_at),
            'archived_at' => $this->getTimestamp($product->deleted_at),
        ]);
    }
}
