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
    ];

    public function includeContacts($client)
    {
        return $this->collection($client->contacts, new ContactTransformer);
    }

    public function includeInvoices($client)
    {
        return $this->collection($client->invoices, new InvoiceTransformer);
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