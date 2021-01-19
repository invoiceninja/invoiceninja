<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */


namespace App\Http\Livewire;

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
        'contact_phone' => 'phone',
    ];

    public $show_form = false;

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
            $this->emit('passed-required-fields-check');

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
                if (empty($this->contact->client->{$_field}) || is_null($this->contact->client->{$_field})) {
                    $this->show_form = true;
                } else {
                    $this->fields[$index]['filled'] = true;
                }
            }

            if (Str::startsWith($field['name'], 'contact_')) {
                if ((empty($this->contact->{$_field}) || is_null($this->contact->{$_field}))) {
                    $this->show_form = true;
                } else {
                    $this->fields[$index]['filled'] = true;
                }
            }
        }
    }

    public function render()
    {
        count($this->fields) > 0
            ? $this->checkFields()
            : $this->show_form = false;

        return render('components.livewire.required-client-info');
    }
}
