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

namespace App\Livewire\BillingPortal\Authentication;

use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ClientRegisterService
{
    use GeneratesCounter;

    public function __construct(
        public Company $company,
        public array $additional = [],
    ) {
    }

    public function rules(): array
    {
        $rules = [];

        foreach ($this->company->client_registration_fields as $field) {
            if ($field['visible'] ?? true) {
                $rules[$field['key']] = $field['required'] ? ['bail', 'required'] : ['sometimes'];
            }
        }

        foreach ($rules as $field => $properties) {
            if ($field == 'email') {
                $rules[$field] = array_merge($rules[$field], ['email:rfc', 'max:191', Rule::unique('client_contacts')->where('company_id', $this->company->id)]);
            }

            if ($field == 'current_password' || $field == 'password') {
                $rules[$field] = array_merge($rules[$field], ['string', 'min:6', 'confirmed']);
            }
        }

        // if ($this->company->settings->client_portal_terms || $this->company->settings->client_portal_privacy_policy) {
        //     $rules['terms'] = ['required'];
        // }

        foreach ($this->additional as $field) {
            if ($field['visible'] ?? true) {
                $rules[$field['key']] = $field['required'] ? ['bail', 'required'] : ['sometimes'];
            }
        }

        return $rules;
    }

    public function createClient(array $data): Client
    {
        $client = ClientFactory::create($this->company->id, $this->company->owner()->id);

        $client->fill($data);

        $client->save();

        if (isset($data['currency_id'])) {
            $settings = $client->settings;
            $settings->currency_id = isset($data['currency_id']) ? $data['currency_id'] : $this->company->settings->currency_id;
            $client->settings = $settings;
        }

        $client->number = $this->getNextClientNumber($client);
        $client->save();

        if (!array_key_exists('country_id', $data) && strlen($client->company->settings->country_id) > 1) {
            $client->update(['country_id' => $client->company->settings->country_id]);
        }

        return $client;
    }

    public function createClientContact(array $data, Client $client): ClientContact
    {
        $client_contact = ClientContactFactory::create($this->company->id, $this->company->owner()->id);
        $client_contact->fill($data);

        $client_contact->client_id = $client->id;
        $client_contact->is_primary = true;

        if (array_key_exists('password', $data)) {
            $client_contact->password = Hash::make($data['password']);
        }

        $client_contact->save();

        return $client_contact;
    }

    public static function mappings(): array
    {
        return [
            'contact_first_name' => 'first_name',
            'contact_last_name' => 'last_name',
            'contact_email' => 'email',
            'client_phone' => 'phone',
            'client_city' => 'city',
            'client_address_line_1' => 'address1',
            'client_address_line_2' => 'address2',
            'client_state' => 'state',
            'client_country_id' => 'country_id',
            'client_postal_code' => 'postal_code',
            'client_shipping_postal_code' => 'shipping_postal_code',
            'client_shipping_address_line_1' => 'shipping_address1',
            'client_shipping_city' => 'shipping_city',
            'client_shipping_state' => 'shipping_state',
            'client_shipping_country_id' => 'shipping_country_id',
            'client_custom_value1' => 'custom_value1',
            'client_custom_value2' => 'custom_value2',
            'client_custom_value3' => 'custom_value3',
            'client_custom_value4' => 'custom_value4',
        ];
    }
}
