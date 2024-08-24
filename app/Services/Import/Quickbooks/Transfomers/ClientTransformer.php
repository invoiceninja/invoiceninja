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

namespace App\Services\Import\Quickbooks\Transformers;

/**
 * Class ClientTransformer.
 */
class ClientTransformer
{
    
    private $fillable = [
        'name'              => 'CompanyName',
        'phone'             => 'PrimaryPhone.FreeFormNumber',
        'country_id'        => 'BillAddr.Country',
        'state'             => 'BillAddr.CountrySubDivisionCode',
        'address1'          => 'BillAddr.Line1',
        'city'              => 'BillAddr.City',
        'postal_code'       => 'BillAddr.PostalCode',
        'shipping_country_id' => 'ShipAddr.Country',
        'shipping_state'    => 'ShipAddr.CountrySubDivisionCode',
        'shipping_address1' => 'ShipAddr.Line1',
        'shipping_city'     => 'ShipAddr.City',
        'shipping_postal_code' => 'ShipAddr.PostalCode',
        'public_notes'      => 'Notes'
    ];

    public function __invoke($qb_data)
    {
        return $this->transform($qb_data);
    }


    public function transform($data)
    {
        $transformed_data = [];
        // Assuming 'customer_name' is equivalent to 'CompanyName'
        if (isset($data['CompanyName']) && $this->hasClient($data['CompanyName'])) {
            return false;
        }

        $transformed_data = $this->preTransform($data);
        $transformed_data['contacts'][0] = $this->getContacts($data)->toArray() + ['company_id' => $this->company->id, 'user_id' => $this->company->owner()->id ];

        return $transformed_data;
    }

    protected function getContacts($data)
    {
        return (new ClientContact())->fill([
                    'first_name'    => $this->getString($data, 'GivenName'),
                    'last_name'     => $this->getString($data, 'FamilyName'),
                    'phone'         => $this->getString($data, 'PrimaryPhone.FreeFormNumber'),
                    'email'         => $this->getString($data, 'PrimaryEmailAddr.Address'),
                    'company_id' => $this->company->id,
                    'user_id' => $this->company->owner()->id,
                    'send_email' => true,
                ]);
    }


    public function getShipAddrCountry($data, $field)
    {
        return is_null(($c = $this->getString($data, $field))) ? null : $this->getCountryId($c);
    }

    public function getBillAddrCountry($data, $field)
    {
        return is_null(($c = $this->getString($data, $field))) ? null : $this->getCountryId($c);
    }

}
