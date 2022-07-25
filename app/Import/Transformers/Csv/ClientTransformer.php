<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2022. client Ninja LLC (https://clientninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformers\Csv;

use App\Import\ImportException;
use App\Import\Transformers\BaseTransformer;
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
        if (isset($data->name) && $this->hasClient($data->name)) {
            throw new ImportException('Client already exists');
        }

        $settings = new \stdClass();
        $settings->currency_id = (string) $this->getCurrencyByCode($data);

        return [
            'company_id' => $this->maps['company']->id,
            'name' => $this->getString($data, 'client.name'),
            'work_phone' => $this->getString($data, 'client.phone'),
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
            'balance' => preg_replace(
                '/[^0-9,.]+/',
                '',
                $this->getFloat($data, 'client.balance')
            ),
            'paid_to_date' => preg_replace(
                '/[^0-9,.]+/',
                '',
                $this->getFloat($data, 'client.paid_to_date')
            ),
            'credit_balance' => 0,
            'settings' => $settings,
            'client_hash' => Str::random(40),
            'contacts' => [
                [
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
                ],
            ],
            'country_id' => isset($data['client.country'])
                ? $this->getCountryId($data['client.country'])
                : null,
            'shipping_country_id' => isset($data['client.shipping_country'])
                ? $this->getCountryId($data['client.shipping_country'])
                : null,
        ];
    }
}
