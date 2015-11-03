<?php namespace App\Ninja\Transformers;

use App\Models\Client;
use App\Models\Contact;
use League\Fractal;
use League\Fractal\TransformerAbstract;

class ClientTransformer extends TransformerAbstract
{
    protected $defaultIncludes = [
        'contacts',
        'invoices',
        'quotes',
    ];

    public function includeContacts($client)
    {
        return $this->collection($client->contacts, new ContactTransformer);
    }

    public function includeInvoices($client)
    {
        $invoices = $client->invoices->filter(function($invoice) {
            return !$invoice->is_quote && !$invoice->is_recurring;
        });
        
        return $this->collection($invoices, new InvoiceTransformer);
    }

    public function includeQuotes($client)
    {
        $invoices = $client->invoices->filter(function($invoice) {
            return $invoice->is_quote && !$invoice->is_recurring;
        });
        
        return $this->collection($invoices, new QuoteTransformer);
    }

    public function transform(Client $client)
    {
        return [
            'id' => (int) $client->public_id,
            'name' => $client->name,
            'balance' => (float) $client->balance,
            'paid_to_date' => (float) $client->paid_to_date,
        ];
    }
}