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
        $transformer = new InvoiceItemTransformer($this->account, $this->serializer);
        return $this->includeCollection($invoice->invoice_items, $transformer, 'invoice_items');
    }

    public function transform(Invoice $invoice)
    {
        return [
            'id' => (int) $invoice->public_id,
            'quote_number' => $invoice->invoice_number,
            'amount' => (float) $invoice->amount,
            'quote_terms' => $invoice->terms,
        ];
    }
}