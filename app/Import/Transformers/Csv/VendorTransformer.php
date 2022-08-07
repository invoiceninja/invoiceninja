<?php

namespace App\Import\Transformers\Csv;

use App\Import\ImportException;
use App\Import\Transformers\BaseTransformer;

/**
 * Class VendorTransformer.
 */
class VendorTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return array|bool
     */
    public function transform($data)
    {
        if (isset($data->name) && $this->hasVendor($data->name)) {
            throw new ImportException('Vendor already exists');
        }

        return [
            'company_id' => $this->maps['company']->id,
            'name'            => $this->getString($data, 'vendor.name'),
            'phone'           => $this->getString($data, 'vendor.phone'),
            'id_number'       => $this->getString($data, 'vendor.id_number'),
            'vat_number'      => $this->getString($data, 'vendor.vat_number'),
            'website'         => $this->getString($data, 'vendor.website'),
            'currency_id'     => $this->getCurrencyByCode($data, 'vendor.currency_id'),
            'public_notes'    => $this->getString($data, 'vendor.public_notes'),
            'private_notes'   => $this->getString($data, 'vendor.private_notes'),
            'address1'        => $this->getString($data, 'vendor.address1'),
            'address2'        => $this->getString($data, 'vendor.address2'),
            'city'            => $this->getString($data, 'vendor.city'),
            'state'           => $this->getString($data, 'vendor.state'),
            'postal_code'     => $this->getString($data, 'vendor.postal_code'),
            'custom_value1'        => $this->getString($data, 'vendor.custom_value1'),
            'custom_value2'        => $this->getString($data, 'vendor.custom_value2'),
            'custom_value3'        => $this->getString($data, 'vendor.custom_value3'),
            'custom_value4'        => $this->getString($data, 'vendor.custom_value4'),
            'vendor_contacts' => [
                [
                    'first_name' => $this->getString($data, 'vendor.first_name'),
                    'last_name'  => $this->getString($data, 'vendor.last_name'),
                    'email'      => $this->getString($data, 'vendor.email'),
                    'phone'      => $this->getString($data, 'vendor.phone'),
                ],
            ],
            'country_id'      => isset($data['vendor.country_id']) ? $this->getCountryId($data['vendor.country_id']) : null,
        ];
    }
}
