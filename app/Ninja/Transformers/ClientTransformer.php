<?php

namespace App\Ninja\Transformers;

use App\Models\Client;

/**
 * @SWG\Definition(definition="Client", @SWG\Xml(name="Client"))
 */
class ClientTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="balance", type="number", format="float", example=10, readOnly=true)
     * @SWG\Property(property="paid_to_date", type="number", format="float", example=10, readOnly=true)
     * @SWG\Property(property="user_id", type="integer", example=1)
     * @SWG\Property(property="account_key", type="string", example="123456")
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="address1", type="string", example="10 Main St.")
     * @SWG\Property(property="address2", type="string", example="1st Floor")
     * @SWG\Property(property="city", type="string", example="New York")
     * @SWG\Property(property="state", type="string", example="NY")
     * @SWG\Property(property="postal_code", type="string", example=10010)
     * @SWG\Property(property="country_id", type="integer", example=840)
     * @SWG\Property(property="work_phone", type="string", example="(212) 555-1212")
     * @SWG\Property(property="private_notes", type="string", example="Notes...")
     * @SWG\Property(property="public_notes", type="string", example="Notes...")
     * @SWG\Property(property="last_login", type="string", format="date-time", example="2016-01-01 12:10:00")
     * @SWG\Property(property="website", type="string", example="http://www.example.com")
     * @SWG\Property(property="industry_id", type="integer", example=1)
     * @SWG\Property(property="size_id", type="integer", example=1)
     * @SWG\Property(property="is_deleted", type="boolean", example=false)
     * @SWG\Property(property="payment_terms", type="integer", example=30)
     * @SWG\Property(property="custom_value1", type="string", example="Value")
     * @SWG\Property(property="custom_value2", type="string", example="Value")
     * @SWG\Property(property="vat_number", type="string", example="123456")
     * @SWG\Property(property="id_number", type="string", example="123456")
     * @SWG\Property(property="language_id", type="integer", example=1)
     */
    protected $defaultIncludes = [
        'contacts',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'invoices',
        'credits',
    ];

    /**
     * @param Client $client
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeContacts(Client $client)
    {
        $transformer = new ContactTransformer($this->account, $this->serializer);

        return $this->includeCollection($client->contacts, $transformer, ENTITY_CONTACT);
    }

    /**
     * @param Client $client
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeInvoices(Client $client)
    {
        $transformer = new InvoiceTransformer($this->account, $this->serializer, $client);

        return $this->includeCollection($client->invoices, $transformer, ENTITY_INVOICE);
    }

    /**
     * @param Client $client
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeCredits(Client $client)
    {
        $transformer = new CreditTransformer($this->account, $this->serializer);

        return $this->includeCollection($client->credits, $transformer, ENTITY_CREDIT);
    }

    /**
     * @param Client $client
     *
     * @return \League\Fractal\Resource\Collection
     */
    public function includeExpenses(Client $client)
    {
        $transformer = new ExpenseTransformer($this->account, $this->serializer);

        return $this->includeCollection($client->expenses, $transformer, ENTITY_EXPENSE);
    }

    /**
     * @param Client $client
     *
     * @return array
     */
    public function transform(Client $client)
    {
        return array_merge($this->getDefaults($client), [
            'id' => (int) $client->public_id,
            'name' => $client->name,
            'balance' => (float) $client->balance,
            'paid_to_date' => (float) $client->paid_to_date,
            'updated_at' => $this->getTimestamp($client->updated_at),
            'archived_at' => $this->getTimestamp($client->deleted_at),
            'address1' => $client->address1,
            'address2' => $client->address2,
            'city' => $client->city,
            'state' => $client->state,
            'postal_code' => $client->postal_code,
            'country_id' => (int) $client->country_id,
            'work_phone' => $client->work_phone,
            'private_notes' => $client->private_notes,
            'public_notes' => $client->public_notes,
            'last_login' => $client->last_login,
            'website' => $client->website,
            'industry_id' => (int) $client->industry_id,
            'size_id' => (int) $client->size_id,
            'is_deleted' => (bool) $client->is_deleted,
            'payment_terms' => (int) $client->payment_terms,
            'vat_number' => $client->vat_number,
            'id_number' => $client->id_number,
            'language_id' => (int) $client->language_id,
            'currency_id' => (int) $client->currency_id,
            'custom_value1' => $client->custom_value1,
            'custom_value2' => $client->custom_value2,
            'invoice_number_counter' => (int) $client->invoice_number_counter,
            'quote_number_counter' => (int) $client->quote_number_counter,
        ]);
    }
}
