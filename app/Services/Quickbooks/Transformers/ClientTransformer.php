<?php

/**
 * Invoice Ninja (https://clientninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

use App\Models\Client;
use App\DataMapper\ClientSettings;
use App\Services\Quickbooks\QuickbooksService;

/**
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
{
    /**
     * qbToNinja
     *
     * @param  mixed $qb_data
     * @return array
     */
    public function qbToNinja(mixed $qb_data, QuickbooksService $qb_service)
    {
        return $this->transform($qb_data);
    }


    /**
     * ninjaToQb
     *
     * Transforms a Invoice Ninja Client to a QuickBooks Client
     *
     * @param  \App\Models\Client $client
     * @param  \App\Services\Quickbooks\QuickbooksService $qb_service
     * @return array
     */
    public function ninjaToQb(Client $client, QuickbooksService $qb_service): array
    {
        $primary_contact = $client->contacts()->orderBy('is_primary', 'desc')->first();

        return [
            'DisplayName' => $client->present()->name(),
            'PrimaryEmailAddr' => [
                'Address' => $primary_contact?->email ?? '',
            ],
            'PrimaryPhone' => [
                'FreeFormNumber' => $primary_contact?->phone ?? '',
            ],
            'CompanyName' => $client->present()->name(),
            'BillAddr' => [
                'Line1' => $client->address1 ?? '',
                'City' => $client->city ?? '',
                'CountrySubDivisionCode' => $client->state ?? '',
                'PostalCode' => $client->postal_code ?? '',
                'Country' => $client->country?->iso_3166_3 ?? '',
            ],
            'ShipAddr' => [
                'Line1' => $client->shipping_address1 ?? '',
                'City' => $client->shipping_city ?? '',
                'CountrySubDivisionCode' => $client->shipping_state ?? '',
                'PostalCode' => $client->shipping_postal_code ?? '',
                'Country' => $client->shipping_country?->iso_3166_3 ?? '',
            ],
            'GivenName' => $primary_contact?->first_name ?? '',
            'FamilyName' => $primary_contact?->last_name ?? '',
            'PrintOnCheckName' => $client->present()->primary_contact_name(),
            'Notes' => $client->public_notes ?? '',
            'BusinessNumber' => $client->id_number ?? '',
            'Active' => $client->deleted_at ? false : true,
            'V4IDPseudonym' => $client->client_hash ?? \Illuminate\Support\Str::random(32),
            'WebAddr' => $client->website ?? '',
        ];

    }


    /**
     * transform
     *
     * Transforms a QuickBooks Client to a Invoice Ninja Client
     *
     * @param  mixed $data
     * @return array
     */
    public function transform(mixed $data): array
    {

        $contact = [
            'first_name' => data_get($data, 'GivenName', ''),
            'last_name' => data_get($data, 'FamilyName', ''),
            'phone' => data_get($data, 'PrimaryPhone.FreeFormNumber', ''),
            'email' =>  data_get($data, 'PrimaryEmailAddr.Address', null),
        ];

        // Get billing address fields
        $bill_addr = data_get($data, 'BillAddr', []);
        $bill_address1 = data_get($bill_addr, 'Line1', '');
        $bill_address2 = data_get($bill_addr, 'Line2', '');
        $bill_city = data_get($bill_addr, 'City', '');
        $bill_state = data_get($bill_addr, 'CountrySubDivisionCode', '');
        $bill_postal_code = data_get($bill_addr, 'PostalCode', '');
        $bill_country = data_get($bill_addr, 'Country', data_get($bill_addr, 'CountryCode', null));

        // Get shipping address fields
        // If ShipAddr is NULL, QuickBooks indicates "same as billing" - copy billing address to shipping
        $ship_addr = data_get($data, 'ShipAddr');
        
        if ($ship_addr === null) {
            // ShipAddr is NULL, so shipping address is same as billing address
            $ship_address1 = $bill_address1;
            $ship_address2 = $bill_address2;
            $ship_city = $bill_city;
            $ship_state = $bill_state;
            $ship_postal_code = $bill_postal_code;
            $ship_country = $bill_country;
        } else {
            // ShipAddr exists, extract the shipping address fields
            $ship_address1 = data_get($ship_addr, 'Line1', '');
            $ship_address2 = data_get($ship_addr, 'Line2', '');
            $ship_city = data_get($ship_addr, 'City', '');
            $ship_state = data_get($ship_addr, 'CountrySubDivisionCode', '');
            $ship_postal_code = data_get($ship_addr, 'PostalCode', '');
            $ship_country = data_get($ship_addr, 'Country', data_get($ship_addr, 'CountryCode', null));
        }

        $client = [
            'id' => data_get($data, 'Id', null),
            'name' => data_get($data, 'CompanyName', ''),
            'address1' => $bill_address1,
            'address2' => $bill_address2,
            'city' => $bill_city,
            'country_id' => $this->resolveCountry($bill_country),
            'state' => $bill_state,
            'postal_code' => $bill_postal_code,
            'shipping_address1' => $ship_address1,
            'shipping_address2' => $ship_address2,
            'shipping_city' => $ship_city,
            'shipping_country_id' => $this->resolveCountry($ship_country),
            'shipping_state' => $ship_state,
            'shipping_postal_code' => $ship_postal_code,
            'client_hash' => data_get($data, 'V4IDPseudonym', \Illuminate\Support\Str::random(32)),
            'vat_number' => data_get($data, 'PrimaryTaxIdentifier', ''),
            'id_number' => data_get($data, 'BusinessNumber', ''),
            'terms' => data_get($data, 'SalesTermRef', false),
            'is_tax_exempt' => !data_get($data, 'Taxable', false),
            'private_notes' => data_get($data, 'Notes', ''),
        ];

        $settings = ClientSettings::defaults();
        $settings->currency_id = (string) $this->resolveCurrency(data_get($data, 'CurrencyRef', $this->company->settings->currency_id));

        $client['settings'] = $settings;

        $new_client_merge = [];

        return [$client, $contact, $new_client_merge];
    }

}
