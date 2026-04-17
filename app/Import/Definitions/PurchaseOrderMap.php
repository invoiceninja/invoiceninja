<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Definitions;

class PurchaseOrderMap
{
    public static function importable()
    {
        return [
            0 => 'purchase_order.number',
            1 => 'purchase_order.user_id',
            2 => 'purchase_order.amount',
            3 => 'purchase_order.balance',
            4 => 'vendor.name',
            5 => 'purchase_order.discount',
            6 => 'purchase_order.po_number',
            7 => 'purchase_order.date',
            8 => 'purchase_order.due_date',
            9 => 'purchase_order.terms',
            10 => 'purchase_order.status',
            11 => 'purchase_order.public_notes',
            12 => 'purchase_order.is_sent',
            13 => 'purchase_order.private_notes',
            14 => 'purchase_order.uses_inclusive_taxes',
            15 => 'purchase_order.tax_name1',
            16 => 'purchase_order.tax_rate1',
            17 => 'purchase_order.tax_name2',
            18 => 'purchase_order.tax_rate2',
            19 => 'purchase_order.tax_name3',
            20 => 'purchase_order.tax_rate3',
            21 => 'purchase_order.is_amount_discount',
            22 => 'purchase_order.footer',
            23 => 'purchase_order.partial',
            24 => 'purchase_order.partial_due_date',
            25 => 'purchase_order.custom_value1',
            26 => 'purchase_order.custom_value2',
            27 => 'purchase_order.custom_value3',
            28 => 'purchase_order.custom_value4',
            29 => 'purchase_order.custom_surcharge1',
            30 => 'purchase_order.custom_surcharge2',
            31 => 'purchase_order.custom_surcharge3',
            32 => 'purchase_order.custom_surcharge4',
            33 => 'purchase_order.exchange_rate',
            34 => 'purchase_order.currency_id',
            35 => 'item.quantity',
            36 => 'item.cost',
            37 => 'item.product_key',
            38 => 'item.notes',
            39 => 'item.discount',
            40 => 'item.is_amount_discount',
            41 => 'item.tax_name1',
            42 => 'item.tax_rate1',
            43 => 'item.tax_name2',
            44 => 'item.tax_rate2',
            45 => 'item.tax_name3',
            46 => 'item.tax_rate3',
            47 => 'item.custom_value1',
            48 => 'item.custom_value2',
            49 => 'item.custom_value3',
            50 => 'item.custom_value4',
            51 => 'item.type_id',
        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.purchase_order_number',
            1 => 'texts.user',
            2 => 'texts.amount',
            3 => 'texts.balance',
            4 => 'texts.vendor',
            5 => 'texts.discount',
            6 => 'texts.po_number',
            7 => 'texts.date',
            8 => 'texts.due_date',
            9 => 'texts.terms',
            10 => 'texts.status',
            11 => 'texts.public_notes',
            12 => 'texts.sent',
            13 => 'texts.private_notes',
            14 => 'texts.uses_inclusive_taxes',
            15 => 'texts.tax_name',
            16 => 'texts.tax_rate',
            17 => 'texts.tax_name',
            18 => 'texts.tax_rate',
            19 => 'texts.tax_name',
            20 => 'texts.tax_rate',
            21 => 'texts.is_amount_discount',
            22 => 'texts.footer',
            23 => 'texts.partial',
            24 => 'texts.partial_due_date',
            25 => 'texts.custom_value1',
            26 => 'texts.custom_value2',
            27 => 'texts.custom_value3',
            28 => 'texts.custom_value4',
            29 => 'texts.surcharge',
            30 => 'texts.surcharge',
            31 => 'texts.surcharge',
            32 => 'texts.surcharge',
            33 => 'texts.exchange_rate',
            34 => 'texts.currency',
            35 => 'texts.quantity',
            36 => 'texts.cost',
            37 => 'texts.product_key',
            38 => 'texts.notes',
            39 => 'texts.discount',
            40 => 'texts.is_amount_discount',
            41 => 'texts.tax_name',
            42 => 'texts.tax_rate',
            43 => 'texts.tax_name',
            44 => 'texts.tax_rate',
            45 => 'texts.tax_name',
            46 => 'texts.tax_rate',
            47 => 'texts.custom_value',
            48 => 'texts.custom_value',
            49 => 'texts.custom_value',
            50 => 'texts.custom_value',
            51 => 'texts.type',
        ];
    }
}
