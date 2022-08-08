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

namespace App\Import\Transformer\Invoicely;

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
        if (isset($data['Client Name']) && $this->hasClient($data['Client Name'])) {
            throw new ImportException('Client already exists');
        }

        $transformed = [
            'company_id'     => $this->company->id,
            'name'           => $this->getString($data, 'Client Name'),
            'phone'     => $this->getString($data, 'Phone'),
            'country_id'     => isset($data['Country']) ? $this->getCountryIdBy2($data['Country']) : null,
            'credit_balance' => 0,
            'settings'       => new \stdClass,
            'client_hash'    => Str::random(40),
            'contacts'       => [
                [
                    'email'         => $this->getString($data, 'Email'),
                    'phone'         => $this->getString($data, 'Phone'),
                ],
            ],
        ];

        return $transformed;
    }
}
