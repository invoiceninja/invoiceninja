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

class InvoiceMap
{
    public static function importable()
    {
        return [
            0 => 'invoice.number',
            1 => 'invoice.user_id',
            2 => 'invoice.amount',
            3 => 'invoice.balance',
            4 => 'client.name',
            5 => 'invoice.discount',
            6 => 'invoice.po_number',
            7 => 'invoice.date',
            8 => 'invoice.due_date',
            9 => 'invoice.terms',
            10 => 'invoice.status',
            11 => 'invoice.public_notes',
            12 => 'invoice.is_sent',
            13 => 'invoice.private_notes',
            14 => 'invoice.uses_inclusive_taxes',
            15 => 'invoice.tax_name1',
            16 => 'invoice.tax_rate1',
            17 => 'invoice.tax_name2',
            18 => 'invoice.tax_rate2',
            19 => 'invoice.tax_name3',
            20 => 'invoice.tax_rate3',
            21 => 'invoice.is_amount_discount',
            22 => 'invoice.footer',
            23 => 'invoice.partial',
            24 => 'invoice.partial_due_date',
            25 => 'invoice.custom_value1',
            26 => 'invoice.custom_value2',
            27 => 'invoice.custom_value3',
            28 => 'invoice.custom_value4',
            29 => 'invoice.custom_surcharge1',
            30 => 'invoice.custom_surcharge2',
            31 => 'invoice.custom_surcharge3',
            32 => 'invoice.custom_surcharge4',
            33 => 'invoice.exchange_rate',
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
            0 => 'texts.invoice_number',
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
