<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Client;

use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use Illuminate\Support\Str;
use Validator;

class RFFService
{
    public array $mappings = [
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

        'client_custom_value1' => 'custom_value1',
        'client_custom_value2' => 'custom_value2',
        'client_custom_value3' => 'custom_value3',
        'client_custom_value4' => 'custom_value4',

        'contact_first_name' => 'first_name',
        'contact_last_name' => 'last_name',
        'contact_email' => 'email',
        // 'contact_phone' => 'phone',
    ];

    public int $unfilled_fields = 0;

    public function __construct(
        public array $fields,
        public string $database,
        public string $company_gateway_id,
    ) {}

    /**
     * Build Laravel validation rules from gateway required-field definitions.
     *
     * Drivers emit key "validation" (historically also "validation_rules").
     * Rule strings may be comma- or pipe-delimited.
     *
     * @param  array<int, array{name: string, validation?: string|array, validation_rules?: string|array, filled?: mixed}>  $fields
     * @return array<string, array<int, string>>
     */
    public static function rulesForFields(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if (array_key_exists('filled', $field)) {
                continue;
            }

            $raw = $field['validation'] ?? $field['validation_rules'] ?? 'required';

            $rules[$field['name']] = is_array($raw)
                ? $raw
                : preg_split('/[|,]/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

            if ($field['name'] === 'contact_email') {
                $rules[$field['name']][] = 'not_regex:/@example\.com$/i';
            }
        }

        return $rules;
    }

    /**
     * True when existing contact/client data already satisfies the gateway field rules.
     *
     * @param  array<int, array{name: string, validation?: string|array, validation_rules?: string|array}>  $fields
     */
    public static function passesExistingValues(ClientContact $contact, array $fields): bool
    {
        if ($fields === []) {
            return true;
        }

        $rff = new self($fields, '', '0');

        return Validator::make(
            $rff->valuesFor($contact),
            self::rulesForFields($fields)
        )->passes();
    }

    public function check(ClientContact $contact): void
    {
        $this->unfilled_fields = self::passesExistingValues($contact, $this->fields)
            ? 0
            : count($this->fields);
    }

    public function handleSubmit(array $data, ClientContact $contact, callable $callback, bool $return_errors = false): bool|array
    {
        MultiDB::setDb($this->database);

        $validator = Validator::make($data, self::rulesForFields($this->fields));

        if ($validator->fails()) {
            if ($return_errors) {
                return $validator->getMessageBag()->getMessages();
            }
            session()->flash('validation_errors', $validator->getMessageBag()->getMessages());

            return false;
        }

        if ($this->update($data, $contact)) {
            $callback();

            return true;
        }

        return false;
    }

    public function update(array $data, ClientContact $_contact): bool
    {
        $client = [];
        $contact = [];

        MultiDB::setDb($this->database);

        foreach ($data as $field => $value) {
            if (Str::startsWith($field, 'client_')) {
                $client[$this->mappings[$field]] = $value;
            }

            if (Str::startsWith($field, 'contact_')) {
                $contact[$this->mappings[$field]] = $value;
            }
        }

        // $_contact->first_name = $data['contact_first_name'] ?? '';
        // $_contact->last_name = $data['contact_last_name'] ?? '';
        // $_contact->client->name = $data['client_name'] ?? '';
        // $_contact->email = $data['contact_email'] ?? '';
        // $_contact->client->phone = $data['client_phone'] ?? '';
        // $_contact->client->address1 = $data['client_address_line_1'] ?? '';
        // $_contact->client->city = $data['client_city'] ?? '';
        // $_contact->client->state = $data['client_state'] ?? '';
        // $_contact->client->country_id = $data['client_country_id'] ?? '';
        // $_contact->client->postal_code = $data['client_postal_code'] ?? '';
        // $_contact->client->shipping_address1 = $data['client_shipping_address_line_1'] ?? '';
        // $_contact->client->shipping_city = $data['client_shipping_city'] ?? '';
        // $_contact->client->shipping_state = $data['client_shipping_state'] ?? '';
        // $_contact->client->shipping_postal_code = $data['client_shipping_postal_code'] ?? '';
        // $_contact->client->shipping_country_id = $data['client_shipping_country_id'] ?? '';
        // $_contact->client->custom_value1 = $data['client_custom_value1'] ?? '';
        // $_contact->client->custom_value2 = $data['client_custom_value2'] ?? '';
        // $_contact->client->custom_value3 = $data['client_custom_value3'] ?? '';
        // $_contact->client->custom_value4 = $data['client_custom_value4'] ?? '';
        // $_contact->push();


        $_contact
            ->fill($contact)
            ->push();

        $_contact->client
            ->fill($client)
            ->push();

        /** @var \App\Models\CompanyGateway $cg */
        $cg = CompanyGateway::find(
            $this->company_gateway_id,
        );

        //@phpstan-ignore-next-line
        if ($cg && $cg->update_details) {
            $payment_gateway = $cg->driver($_contact->client)->init();

            if (method_exists($payment_gateway, "updateCustomer")) {
                $payment_gateway->updateCustomer();
            }
        }

        return true;
    }

    /**
     * Hydrate current contact/client attributes into gateway field-name keys.
     *
     * @return array<string, mixed>
     */
    private function valuesFor(ClientContact $contact): array
    {
        $data = [];

        foreach ($this->fields as $field) {
            $name = $field['name'];
            $column = $this->mappings[$name] ?? null;

            if ($column === null) {
                continue;
            }

            if (Str::startsWith($name, 'client_')) {
                $data[$name] = $contact->client->{$column};
            } elseif (Str::startsWith($name, 'contact_')) {
                $data[$name] = $contact->{$column};
            }
        }

        return $data;
    }
}
