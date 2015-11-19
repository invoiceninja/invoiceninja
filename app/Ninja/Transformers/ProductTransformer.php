<?php namespace App\Ninja\Transformers;

use App\Models\Product;
use League\Fractal;

class ProductTransformer extends EntityTransformer
{
    public function transform(Product $product)
    {
        return [
            'id' => (int) $product->public_id,
            'product_key' => $product->product_key,
            'notes' => $product->notes,
            'cost' => $product->cost,
            'qty' => $product->qty,
        ];
    }
}