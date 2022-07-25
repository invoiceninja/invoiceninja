<?php
/**
 * Invoice Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2022. client Ninja LLC (https://clientninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Wave;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use Illuminate\Support\Str;

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
        if (isset($data['vendor_name']) && $this->hasVendor($data['vendor_name'])) {
            throw new ImportException('Vendor already exists');
        }

        return [
            'company_id'     => $this->company->id,
            'name'           => $this->getString($data, 'vendor_name'),
            'number'         => $this->getValueOrNull($data, 'account_number'),
            'phone'     => $this->getString($data, 'phone'),
            'website'     	 => $this->getString($data, 'website'),
            'country_id'     => ! empty($data['country']) ? $this->getCountryId($data['country']) : null,
            'state'          => $this->getString($data, 'province/state'),
            'address1'       => $this->getString($data, 'address_line_1'),
            'address2'       => $this->getString($data, 'address_line_2'),
            'city'           => $this->getString($data, 'city'),
            'postal_code'    => $this->getString($data, 'postal_code/zip_code'),
            'currency_id' 	 => $this->getCurrencyByCode($data, 'vendor_currency'),
            'client_hash'    => Str::random(40),
            'contacts'       => [
                [
                    'first_name'    => $this->getString($data, 'contact_first_name'),
                    'last_name'     => $this->getString($data, 'contact_last_name'),
                    'email'         => $this->getString($data, 'email'),
                    'phone'         => $this->getString($data, 'phone'),
                ],
            ],
        ];
    }
}
