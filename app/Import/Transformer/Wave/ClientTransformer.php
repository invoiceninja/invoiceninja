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
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return array|bool
     */
    public function transform($data)
    {
        if (isset($data['customer_name']) && $this->hasClient($data['customer_name'])) {
            throw new ImportException('Client already exists');
        }

        $settings = new \stdClass;
        $settings->currency_id = (string) $this->getCurrencyByCode($data, 'customer_currency');

        if (strval($data['Payment Terms'] ?? '') > 0) {
            $settings->payment_terms = $data['Payment Terms'];
        }

        return [
            'company_id'     => $this->company->id,
            'name'           => $this->getString($data, 'customer_name'),
            'number'         => $this->getValueOrNull($data, 'account_number'),
            'work_phone'     => $this->getString($data, 'phone'),
            'website'     	 => $this->getString($data, 'website'),
            'country_id'     => ! empty($data['country']) ? $this->getCountryId($data['country']) : null,
            'state'          => $this->getString($data, 'province/state'),
            'address1'       => $this->getString($data, 'address_line_1'),
            'address2'       => $this->getString($data, 'address_line_2'),
            'city'           => $this->getString($data, 'city'),
            'postal_code'    => $this->getString($data, 'postal_code/zip_code'),

            'shipping_country_id'     => ! empty($data['ship-to_country']) ? $this->getCountryId($data['country']) : null,
            'shipping_state'          => $this->getString($data, 'ship-to_province/state'),
            'shipping_address1'       => $this->getString($data, 'ship-to_address_line_1'),
            'shipping_address2'       => $this->getString($data, 'ship-to_address_line_2'),
            'shipping_city'           => $this->getString($data, 'ship-to_city'),
            'shipping_postal_code'    => $this->getString($data, 'ship-to_postal_code/zip_code'),
            'public_notes'    		  => $this->getString($data, 'delivery_instructions'),

            'credit_balance' => 0,
            'settings'       =>$settings,
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
