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

namespace App\Import\Transformer\Csv;

use App\DataMapper\ClientSettings;
use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use Illuminate\Support\Str;

/**
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return array|bool
     */
    public function transform($client_data)
    {
        $data = reset($client_data);

        if (isset($data['client.name']) && $this->hasClient($data['client.name'])) {
            throw new ImportException('Client already exists');
        }

        if (!is_array($data)) {
            throw new ImportException('Empty row, or invalid data encountered.');
        }

        $settings = ClientSettings::defaults();
        $settings->currency_id = (string) $this->getCurrencyByCode($data);

        $client = [
            'company_id' => $this->company->id,
            'name' => $this->getString($data, 'client.name'),
            'phone' => $this->getString($data, 'client.phone'),
            'address1' => $this->getString($data, 'client.address1'),
            'address2' => $this->getString($data, 'client.address2'),
            'postal_code' => $this->getString($data, 'client.postal_code'),
            'city' => $this->getString($data, 'client.city'),
            'state' => $this->getString($data, 'client.state'),
            'shipping_address1' => $this->getString(
                $data,
                'client.shipping_address1'
            ),
            'shipping_address2' => $this->getString(
                $data,
                'client.shipping_address2'
            ),
            'shipping_city' => $this->getString($data, 'client.shipping_city'),
            'shipping_state' => $this->getString(
                $data,
                'client.shipping_state'
            ),
            'shipping_postal_code' => $this->getString(
                $data,
                'client.shipping_postal_code'
            ),
            'public_notes' => $this->getString($data, 'client.public_notes'),
            'private_notes' => $this->getString($data, 'client.private_notes'),
            'website' => $this->getString($data, 'client.website'),
            'vat_number' => $this->getString($data, 'client.vat_number'),
            'id_number' => $this->getString($data, 'client.id_number'),
            'custom_value1' => $this->getString($data, 'client.custom_value1'),
            'custom_value2' => $this->getString($data, 'client.custom_value2'),
            'custom_value3' => $this->getString($data, 'client.custom_value3'),
            'custom_value4' => $this->getString($data, 'client.custom_value4'),
            'paid_to_date' => 0,
            'balance' => 0,
            'credit_balance' => 0,
            'settings' => $settings,
            'client_hash' => Str::random(40),
            'country_id' => $this->resolveCountryIdOrCompanyDefault(
                $data,
                'client.country_id'
            ),
            'shipping_country_id' => $this->resolveCountryIdOrCompanyDefault(
                $data,
                ['client.shipping_country_id', 'client.shipping_country']
            ),
        ];

        $contacts = [];

        foreach ($client_data as $data) {
            $contacts[] = [
                'first_name' => $this->getString(
                    $data,
                    'contact.first_name'
                ),
                'last_name' => $this->getString($data, 'contact.last_name'),
                'email' => $this->getString($data, 'contact.email'),
                'phone' => $this->getString($data, 'contact.phone'),
                'custom_value1' => $this->getString(
                    $data,
                    'contact.custom_value1'
                ),
                'custom_value2' => $this->getString(
                    $data,
                    'contact.custom_value2'
                ),
                'custom_value3' => $this->getString(
                    $data,
                    'contact.custom_value3'
                ),
                'custom_value4' => $this->getString(
                    $data,
                    'contact.custom_value4'
                ),
            ];
        }

        $client['contacts'] = $contacts;

        return $client;

    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string>|string $keys
     */
    private function resolveCountryIdOrCompanyDefault(array $data, array|string $keys): int|string|null
    {
        foreach ((array) $keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $country = trim((string) $data[$key]);

            if ($country === '') {
                return $this->company->settings->country_id;
            }

            $country_id = $this->getCountryId($country);

            if ($country_id === null) {
                return $this->company->settings->country_id;
            }

            return $country_id;
        }

        return $this->company->settings->country_id;
    }
}
