<?php namespace App\Ninja\Transformers;

use App\Models\InvoiceItem;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class InvoiceItemTransformer extends TransformerAbstract
{
    public function transform(InvoiceItem $item)
    {
        return [
            'public_id' => (int) $item->public_id,
            'product_key' => $item->product_key,
            'account_key' => $item->account->account_key,
            'user_id' => (int) $item->user_id,
            'invoice_id' => (int) $item->invoice_id,
            'product_id' => (int) $item->product_id,
            'updated_at' => $item->updated_at,
            'deleted_at' => $item->deleted_at,
            'product_key' => $item->product_key,
            'notes' => $item->notes,
            'cost' => (float) $item->cost,
            'qty' => (float) $item->qty,
            'tax_name' => $item->tax_name,
            'tax_rate' => $item->tax_rate
        ];
    }
}