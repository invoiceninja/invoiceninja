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
            if ($field === 'email') {
                $rules[$field] = array_merge($rules[$field], ['email:rfc,dns', 'max:191', Rule::unique('client_contacts')->where('company_id', $this->company->id)]);
            }

            if ($field === 'current_password' || $field === 'password') {
                $rules[$field] = array_merge($rules[$field], ['string', 'min:6', 'confirmed']);
            }
        }

        if ($this->company->settings->client_portal_terms || $this->company->settings->client_portal_privacy_policy) {
            $rules['terms'] = ['required'];
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
}