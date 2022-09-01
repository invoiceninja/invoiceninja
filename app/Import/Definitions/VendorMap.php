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

namespace App\Import\Definitions;

class VendorMap
{
    public static function importable()
    {
        return [
            0 => 'vendor.name',
            1 => 'vendor.phone',
            2 => 'vendor.id_number',
            3 => 'vendor.vat_number',
            4 => 'vendor.website',
            5 => 'vendor.first_name',
            6 => 'vendor.last_name',
            7 => 'vendor.email',
            8 => 'vendor.currency_id',
            9 => 'vendor.public_notes',
            10 => 'vendor.private_notes',
            11 => 'vendor.address1',
            12 => 'vendor.address2',
            13 => 'vendor.city',
            14 => 'vendor.state',
            15 => 'vendor.postal_code',
            16 => 'vendor.country_id',
            17 => 'contact.first_name',
            18 => 'contact.last_name',
            19 => 'contact.email',
            20 => 'contact.phone',
            21 => 'contact.custom_value1',
            22 => 'contact.custom_value2',
            23 => 'contact.custom_value3',
            24 => 'contact.custom_value4',

        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.name',
            1 => 'texts.phone',
            2 => 'texts.id_number',
            3 => 'texts.vat_number',
            4 => 'texts.website',
            5 => 'texts.first_name',
            6 => 'texts.last_name',
            7 => 'texts.email',
            8 => 'texts.currency',
            9 => 'texts.public_notes',
            10 => 'texts.private_notes',
            11 => 'texts.address1',
            12 => 'texts.address2',
            13 => 'texts.city',
            14 => 'texts.state',
            15 => 'texts.postal_code',
            16 => 'texts.country',
            17 => 'texts.first_name',
            18 => 'texts.last_name',
            19 => 'texts.email',
            20 => 'texts.phone',
            21 => 'texts.custom_value',
            22 => 'texts.custom_value',
            23 => 'texts.custom_value',
            24 => 'texts.custom_value',
        ];
    }
}
