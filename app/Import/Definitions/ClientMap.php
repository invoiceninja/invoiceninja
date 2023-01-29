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
            29 => 'client.phone',
            30 => 'contact.first_name',
            31 => 'contact.last_name',
            32 => 'contact.email',
            33 => 'contact.phone',
            34 => 'contact.custom_value1',
            35 => 'contact.custom_value2',
            36 => 'contact.custom_value3',
            37 => 'contact.custom_value4',
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
            19 => 'texts.shipping_address1',
            20 => 'texts.shipping_address2',
            21 => 'texts.shipping_city',
            22 => 'texts.shipping_state',
            23 => 'texts.shipping_postal_code',
            24 => 'texts.shipping_country',
            25 => 'texts.payment_terms',
            26 => 'texts.vat_number',
            27 => 'texts.id_number',
            28 => 'texts.public_notes',
            29 => 'texts.client_phone',
            30 => 'texts.first_name',
            31 => 'texts.last_name',
            32 => 'texts.email',
            33 => 'texts.phone',
            34 => 'texts.custom_value',
            35 => 'texts.custom_value',
            36 => 'texts.custom_value',
            37 => 'texts.custom_value',
        ];
    }
}
