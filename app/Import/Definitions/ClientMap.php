<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2021. client Ninja LLC (https://clientninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Import\Definitions;

class ClientMap
{
    public static function importable()
    {
        return [
            0 => 'client.name',
            1 => 'client.user_id',
            2 => 'client.balance',
            3 => 'client.paid_to_date',
            4 => 'client.currency_id',
            5 => 'client.website',
            6 => 'client.private_notes',
            7 => 'client.industry_id',
            8 => 'client.size_id',
            9 => 'client.address1',
            10 => 'client.address2',
            11 => 'client.city',
            12 => 'client.state',
            13 => 'client.postal_code',
            14 => 'client.country_id',
            15 => 'client.custom_value1',
            16 => 'client.custom_value2',
            17 => 'client.custom_value3',
            18 => 'client.custom_value4',
            19 => 'client.shipping_address1',
            20 => 'client.shipping_address2',
            21 => 'client.shipping_city',
            22 => 'client.shipping_state',
            23 => 'client.shipping_postal_code',
            24 => 'client.shipping_country_id',
            25 => 'client.payment_terms',
            26 => 'client.vat_number',
            27 => 'client.id_number',
            28 => 'client.public_notes',
            29 => 'contact.first_name',
            30 => 'contact.last_name',
            31 => 'contact.email',
            32 => 'contact.phone',
            33 => 'contact.custom_value1',
            34 => 'contact.custom_value2',
            35 => 'contact.custom_value3',
            36 => 'contact.custom_value4',
        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.client_name',
            1 => 'texts.user',
            2 => 'texts.balance',
            3 => 'texts.paid_to_date',
            4 => 'texts.currency',
            5 => 'texts.website',
            6 => 'texts.private_notes',
            7 => 'texts.industry',
            8 => 'texts.size',
            9 => 'texts.address1',
            10 => 'texts.address2',
            11 => 'texts.city',
            12 => 'texts.state',
            13 => 'texts.postal_code',
            14 => 'texts.country',
            15 => 'texts.custom_value',
            16 => 'texts.custom_value',
            17 => 'texts.custom_value',
            18 => 'texts.custom_value',
            19 => 'texts.address1',
            20 => 'texts.address2',
            21 => 'texts.shipping_city',
            22 => 'texts.shipping_state',
            23 => 'texts.shipping_postal_code',
            24 => 'texts.shipping_country',
            25 => 'texts.payment_terms',
            26 => 'texts.vat_number',
            27 => 'texts.id_number',
            28 => 'texts.public_notes',
            29 => 'texts.first_name',
            30 => 'texts.last_name',
            31 => 'texts.email',
            32 => 'texts.phone',
            33 => 'texts.custom_value',
            34 => 'texts.custom_value',
            35 => 'texts.custom_value',
            36 => 'texts.custom_value',
        ];
    }
}
