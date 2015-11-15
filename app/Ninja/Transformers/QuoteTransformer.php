<?php namespace App\Ninja\Transformers;

use App\Models\Invoice;
use League\Fractal;

class QuoteTransformer extends EntityTransformer
{
    protected $defaultIncludes = [
        'invoice_items',
    ];

    public function includeInvoiceItems($invoice)
    {
        return $this->collection($invoice->invoice_items, new InvoiceItemTransformer($this->account));
    }

    public function transform(Invoice $invoice)
    {
        return [
            'id' => (int) $invoice->public_id,
            'quote_number' => $invoice->invoice_number,
            'amount' => (float) $invoice->amount,
        ];
    }
}