<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */


namespace App\Http\Livewire;

use App\Libraries\MultiDB;
use App\Models\ClientContact;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Component;

class RequiredClientInfo extends Component
{
    public $fields = [];

    /**
     * @var ClientContact
     */
    public $contact;

    public $client;

    /**
     * @var array
     */
    public $countries;

    /**
     * Mappings for updating the database. Left side is mapping from gateway,
     * right side is column in database.
     *
     * @var string[]
     */
    private $mappings = [
        'client_name' => 'name',
        'client_website' => 'website',
        'client_phone' => 'phone',

        'client_address_line_1' => 'address1',
        'client_address_line_2' => 'address2',
        'client_city' => 'city',
        'client_state' => 'state',
        'client_postal_code' => 'postal_code',
        'client_country_id' => 'country_id',

        'client_shipping_address_line_1' => 'shipping_address1',
        'client_shipping_address_line_2' => 'shipping_address2',
        'client_shipping_city' => 'shipping_city',
        'client_shipping_state' => 'shipping_state',
        'client_shipping_postal_code' => 'shipping_postal_code',
        'client_shipping_country_id' => 'shipping_country_id',

        'contact_first_name' => 'first_name',
        'contact_last_name' => 'last_name',
        'contact_email' => 'email',
        // 'contact_phone' => 'phone',
    ];

    public $client_address_array = [
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_id',
    ];

    protected $rules = [
        'client.address1' => '',
        'client.address2' => '',
        'client.city' => '',
        'client.state' => '',
        'client.postal_code' => '',
        'client.country_id' => '',
        'client.shipping_address1' => '',
        'client.shipping_address2' => '',
        'client.shipping_city' => '',
        'client.shipping_state' => '',
        'client.shipping_postal_code' => '',
        'client.shipping_country_id' => '',
        'contact.first_name' => '',
        'contact.last_name' => '',
        'contact.email' => '',
        'client.name' => '',
        'client.website' => '',
        'client.phone' => '',
    ];

    public $show_form = false;

    public $company;
    
    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->client = $this->contact->client;

        count($this->fields) > 0
            ? $this->checkFields()
            : $this->show_form = false;

    }

    public function handleSubmit(array $data): bool
    {
        $rules = [];

        collect($this->fields)->map(function ($field) use (&$rules) {
            if (!array_key_exists('filled', $field)) {
                $rules[$field['name']] = array_key_exists('validation_rules', $field)
                    ? $field['validation_rules']
                    : 'required';
            }
        });

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            session()->flash('validation_errors', $validator->getMessageBag()->getMessages());

            return false;
        }

        if ($this->updateClientDetails($data)) {
            $this->emit('passed-required-fields-check', [
                'client_postal_code' => $this->contact->client->postal_code,
            ]);

            return true;
        }

        // TODO: Throw an exception about not being able to update the profile.
        return false;
    }

    private function updateClientDetails(array $data): bool
    {
        $client = [];
        $contact = [];

        foreach ($data as $field => $value) {
            if (Str::startsWith($field, 'client_')) {
                $client[$this->mappings[$field]] = $value;
            }

            if (Str::startsWith($field, 'contact_')) {
                $contact[$this->mappings[$field]] = $value;
            }
        }

        $contact_update = $this->contact
            ->fill($contact)
            ->push();

        $client_update = $this->contact->client
            ->fill($client)
            ->push();

        if ($contact_update && $client_update) {
            return true;
        }

        return false;
    }

    public function checkFields()
    {
        foreach ($this->fields as $index => $field) {
            $_field = $this->mappings[$field['name']];

            if (Str::startsWith($field['name'], 'client_')) {
                if (empty($this->contact->client->{$_field}) || is_null($this->contact->client->{$_field}) || in_array($_field, $this->client_address_array)) {
                    $this->show_form = true;
                } else {
                    $this->fields[$index]['filled'] = true;
                }
            }

            if (Str::startsWith($field['name'], 'contact_')) {
                if (empty($this->contact->{$_field}) || is_null($this->contact->{$_field})) {
                    $this->show_form = true;
                } else {
                    $this->fields[$index]['filled'] = true;
                }
            }
        }
    }

    public function showCopyBillingCheckbox(): bool
    {
        $fields = [];

        collect($this->fields)->map(function ($field) use (&$fields) {
            if (! array_key_exists('filled', $field)) {
                $fields[] = $field['name'];
            }
        });

        foreach ($fields as $field) {
            if (Str::startsWith($field, 'client_shipping')) {
                return true;
            }
        }

        return false;
    }

    public function handleCopyBilling(): void
    {
        $this->emit('update-shipping-data', [
            'client_shipping_address_line_1' => $this->contact->client->address1,
            'client_shipping_address_line_2' => $this->contact->client->address2,
            'client_shipping_city' => $this->contact->client->city,
            'client_shipping_state' => $this->contact->client->state,
            'client_shipping_postal_code' => $this->contact->client->postal_code,
            'client_shipping_country_id' => $this->contact->client->country_id,
        ]); 
    }

    public function render()
    {
        return render('components.livewire.required-client-info');
    }
}
