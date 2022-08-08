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

namespace App\Import\Transformer\Freshbooks;

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
        if (isset($data['Organization']) && $this->hasClient($data['Organization'])) {
            throw new ImportException('Client already exists');
        }

        return [
            'company_id'     => $this->company->id,
            'name'           => $this->getString($data, 'Organization'),
            'phone'     => $this->getString($data, 'Phone'),
            'address1'       => $this->getString($data, 'Street'),
            'city'           => $this->getString($data, 'City'),
            'state'          => $this->getString($data, 'Province/State'),
            'postal_code'    => $this->getString($data, 'Postal Code'),
            'country_id'     => isset($data['Country']) ? $this->getCountryId($data['Country']) : null,
            'private_notes'   => $this->getString($data, 'Notes'),
            'credit_balance' => 0,
            'settings'       => new \stdClass,
            'client_hash'    => Str::random(40),
            'contacts'       => [
                [
                    'first_name'    => $this->getString($data, 'First Name'),
                    'last_name'     => $this->getString($data, 'Last Name'),
                    'email'         => $this->getString($data, 'Email'),
                    'phone'         => $this->getString($data, 'Phone'),
                ],
            ],
        ];
    }
}
