<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\Client;
use App\Models\Contact;
use League\Fractal;

/**
 * @SWG\Definition(definition="Client", @SWG\Xml(name="Client"))
 */

class ClientTransformer extends EntityTransformer
{
    /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="balance", type="float", example=10, readOnly=true)
    * @SWG\Property(property="paid_to_date", type="float", example=10, readOnly=true)
    */

    protected $defaultIncludes = [
        'contacts',
        'invoices',
        'quotes',
    ];
    
    public function includeContacts(Client $client)
    {
        return $this->collection($client->contacts, new ContactTransformer($this->account));
    }

    public function includeInvoices(Client $client)
    {
        return $this->collection($client->getInvoices, new InvoiceTransformer($this->account, $client));
    }

    public function includeQuotes(Client $client)
    {
        return $this->collection($client->getQuotes, new QuoteTransformer($this->account, $client));
    }

    public function transform(Client $client)
    {
        return [
            'public_id' => (int) $client->public_id,
            'name' => $client->name,
            'balance' => (float) $client->balance,
            'paid_to_date' => (float) $client->paid_to_date,
            'user_id' => (int) $client->user_id,
            'account_key' => $this->account->account_key,
            'updated_at' => $client->updated_at,
            'deleted_at' => $client->deleted_at,
            'address1' => $client->address1,
            'address2' => $client->address2,
            'city' => $client->city,
            'state' => $client->state,
            'postal_code' => $client->postal_code,
            'country_id' => (int) $client->country_id,
            'work_phone' => $client->work_phone,
            'private_notes' => $client->private_notes,
            'balance' => (float) $client->balance,
            'paid_to_date' => $client->paid_to_date,
            'last_login' => $client->last_login,
            'website' => $client->website,
            'industry_id' => (int) $client->industry_id,
            'size_id' => (int) $client->size_id,
            'is_deleted' => (bool) $client->is_deleted,
            'payment_terms' => (int) $client->payment_terms,
            'vat_number' => $client->vat_number,
            'id_number' => $client->id_number,
            'language_id' => (int) $client->language_id
        ];
    }
}