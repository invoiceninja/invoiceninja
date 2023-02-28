<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper;

class ClientRegistrationFields
{
    public static function generate()
    {
        $data =
        [
            [
                'key' => 'first_name',
                'required' => true,
                'visible' => true,
            ],
            [
                'key' => 'last_name',
                'required' => true,
                'visible' => true,
            ],
            [
                'key' => 'email',
                'required' => true,
                'visible' => true,
            ],
            [
                'key' => 'phone',
                'required' => false,
                'visible' => true,
            ],
            [
                'key' => 'password',
                'required' => true,
                'visible' => true,
            ],
            [
                'key' => 'name',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'website',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'address1',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'address2',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'city',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'state',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'postal_code',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'country_id',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'custom_value1',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'custom_value2',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'custom_value3',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'custom_value4',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'public_notes',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'vat_number',
                'required' => false,
                'visible' => false,
            ],
            [
                'key' => 'currency_id',
                'required' => false,
                'visible' => false,
            ],
        ];

        return $data;
    }
}
