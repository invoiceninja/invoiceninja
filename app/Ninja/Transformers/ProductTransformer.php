<?php namespace App\Ninja\Transformers;

use App\Models\Product;

class ProductTransformer extends EntityTransformer
{
    public function transform(Product $product)
    {
        return array_merge($this->getDefaults($product), [
            'id' => (int) $product->public_id,
            'product_key' => $product->product_key,
            'notes' => $product->notes,
            'cost' => $product->cost,
            'qty' => $product->qty,
            'default_tax_rate_id' =>$product->default_tax_rate_id,
            'updated_at' =>$this->getTimestamp($product->updated_at),
            'archived_at' => $this->getTimestamp($product->deleted_at),
        ]);
    }
}