<?php
/**
 * Invoice Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Definitions;

class QuoteMap
{
    public static function importable()
    {
        return [
            0 => 'quote.number',
            1 => 'quote.user_id',
            2 => 'quote.amount',
            3 => 'quote.balance',
            4 => 'client.name',
            5 => 'quote.discount',
            6 => 'quote.po_number',
            7 => 'quote.date',
            8 => 'quote.due_date',
            9 => 'quote.terms',
            10 => 'quote.status',
            11 => 'quote.public_notes',
            12 => 'quote.is_sent',
            13 => 'quote.private_notes',
            14 => 'quote.uses_inclusive_taxes',
            15 => 'quote.tax_name1',
            16 => 'quote.tax_rate1',
            17 => 'quote.tax_name2',
            18 => 'quote.tax_rate2',
            19 => 'quote.tax_name3',
            20 => 'quote.tax_rate3',
            21 => 'quote.is_amount_discount',
            22 => 'quote.footer',
            23 => 'quote.partial',
            24 => 'quote.partial_due_date',
            25 => 'quote.custom_value1',
            26 => 'quote.custom_value2',
            27 => 'quote.custom_value3',
            28 => 'quote.custom_value4',
            29 => 'quote.custom_surcharge1',
            30 => 'quote.custom_surcharge2',
            31 => 'quote.custom_surcharge3',
            32 => 'quote.custom_surcharge4',
            33 => 'quote.exchange_rate',
            34 => 'payment.date',
            35 => 'payment.amount',
            36 => 'payment.transaction_reference',
            37 => 'item.quantity',
            38 => 'item.cost',
            39 => 'item.product_key',
            40 => 'item.notes',
            41 => 'item.discount',
            42 => 'item.is_amount_discount',
            43 => 'item.tax_name1',
            44 => 'item.tax_rate1',
            45 => 'item.tax_name2',
            46 => 'item.tax_rate2',
            47 => 'item.tax_name3',
            48 => 'item.tax_rate3',
            49 => 'item.custom_value1',
            50 => 'item.custom_value2',
            51 => 'item.custom_value3',
            52 => 'item.custom_value4',
            53 => 'item.type_id',
            54 => 'client.email',
        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.quote_number',
            1 => 'texts.user',
            2 => 'texts.amount',
            3 => 'texts.balance',
            4 => 'texts.client',
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
            34 => 'texts.payment_date',
            35 => 'texts.payment_amount',
            36 => 'texts.transaction_reference',
            37 => 'texts.quantity',
            38 => 'texts.cost',
            39 => 'texts.product_key',
            40 => 'texts.notes',
            41 => 'texts.discount',
            42 => 'texts.is_amount_discount',
            43 => 'texts.tax_name',
            44 => 'texts.tax_rate',
            45 => 'texts.tax_name',
            46 => 'texts.tax_rate',
            47 => 'texts.tax_name',
            48 => 'texts.tax_rate',
            49 => 'texts.custom_value',
            50 => 'texts.custom_value',
            51 => 'texts.custom_value',
            52 => 'texts.custom_value',
            53 => 'texts.type',
            54 => 'texts.email',
        ];
    }
}
