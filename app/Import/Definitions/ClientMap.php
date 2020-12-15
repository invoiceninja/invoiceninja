<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2020. client Ninja LLC (https://clientninja.com)
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
            28 => 'client.public_notes'
        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.client_name',
            1 => 'texts.user',
            2 => 'texts.amount',
            3 => 'texts.balance',
            4 => 'texts.client',
            5 => 'texts.discount',
            6 => 'texts.po_number',
            7 => 'texts.date',
            8 => 'texts.due_date',
            9 => 'texts.terms',
            10 => 'texts.public_notes',
            11 => 'texts.sent',
            12 => 'texts.private_notes',
            13 => 'texts.uses_inclusive_taxes',
            14 => 'texts.tax_name',
            15 => 'texts.tax_rate',
            16 => 'texts.tax_name',
            17 => 'texts.tax_rate',
            18 => 'texts.tax_name',
            19 => 'texts.tax_rate',
            20 => 'texts.is_amount_discount',
            21 => 'texts.footer',
            22 => 'texts.partial',
            23 => 'texts.partial_due_date',
            24 => 'texts.custom_value1',
            25 => 'texts.custom_value2',
            26 => 'texts.custom_value3',
            27 => 'texts.custom_value4',
            28 => 'texts.surcharge',
            29 => 'texts.surcharge',
            30 => 'texts.surcharge',
            31 => 'texts.surcharge',
            32 => 'texts.exchange_rate',
            33 => 'texts.payment_date',
            34 => 'texts.payment_amount',
            35 => 'texts.transaction_reference',
            36 => 'texts.quantity',
            37 => 'texts.cost',
            38 => 'texts.product_key',
            39 => 'texts.notes',
            40 => 'texts.discount',
            41 => 'texts.is_amount_discount',
            42 => 'texts.tax_name',
            43 => 'texts.tax_rate',
            44 => 'texts.tax_name',
            45 => 'texts.tax_rate',
            46 => 'texts.tax_name',
            47 => 'texts.tax_rate',
            48 => 'texts.custom_value',
            49 => 'texts.custom_value',
            50 => 'texts.custom_value',
            51 => 'texts.custom_value',
            52 => 'texts.type',
        ];
    }
}
