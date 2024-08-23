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

namespace App\Import\Transformer\Quickbooks;

use App\Import\Transformer\Quickbooks\CommonTrait;
use App\Import\Transformer\BaseTransformer;
use App\Models\Client as Model;
use App\Models\ClientContact;
use App\Import\ImportException;
use Illuminate\Support\Str;

/**
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
{
    use CommonTrait {
        transform as preTransform;
    }

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

    public function __construct($company)
    {
        parent::__construct($company);

        $this->model = new Model();
    }


    /**
     * Transforms a Customer array into a ClientContact model.
     *
     * @param array $data
     * @return array|bool
     */
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
