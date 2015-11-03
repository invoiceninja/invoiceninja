<?php namespace App\Ninja\Transformers;

use App\Models\InvoiceItem;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class InvoiceItemTransformer extends TransformerAbstract
{
    public function transform(InvoiceItem $item)
    {
        return [
            'id' => (int) $item->public_id,
            'product_key' => $item->product_key,
        ];
    }
}