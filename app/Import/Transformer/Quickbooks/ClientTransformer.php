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

use App\Import\Transformer\BaseTransformer;
use App\Models\Client as Model;
use App\Models\ClientContact;
use App\Import\ImportException;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

/**
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
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

        foreach($this->fillable as $key => $field) {
            $transformed_data[$key] = method_exists($this, $method = sprintf("get%s", str_replace(".","",$field)) )? call_user_func([$this, $method],$data,$field) :  $this->getString($data, $field);
        }
        
        $transformed_data = (new Model)->fillable(array_keys($this->fillable))->fill($transformed_data)->toArray() + $this->getContacts($data, $field);

        return $transformed_data;
    }

    public function getString($data, $field)
    {
        return Arr::get($data, $field);
    }

    protected function getContacts($data, $field = null) {
        return [ 'contacts' => [
                (new ClientContact())->fill([
                    'first_name'    => $this->getString($data, 'GivenName'),
                    'last_name'     => $this->getString($data, 'FamilyName'),
                    'phone'         => $this->getString($data, 'PrimaryPhone.FreeFormNumber'),
                    'email'         => $this->getString($data, 'PrimaryEmailAddr.Address'),
                ]) ]
            ];
    }


    public function getShipAddrCountry($data,$field) {
        return is_null(($c = $this->getString($data,$field))) ? null : $this->getCountryId($c);
    }

    public function getBillAddrCountry($data,$field) {
        return is_null(($c = $this->getString($data,$field))) ? null : $this->getCountryId($c);
    }

}
