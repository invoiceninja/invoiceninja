<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
            10 => 'invoice.public_notes',
            11 => 'invoice.is_sent',
            12 => 'invoice.private_notes',
            13 => 'invoice.uses_inclusive_taxes',
            14 => 'invoice.tax_name1',
            15 => 'invoice.tax_rate1',
            16 => 'invoice.tax_name2',
            17 => 'invoice.tax_rate2',
            18 => 'invoice.tax_name3',
            19 => 'invoice.tax_rate3',
            20 => 'invoice.is_amount_discount',
            21 => 'invoice.footer',
            22 => 'invoice.partial',
            23 => 'invoice.partial_due_date',
            24 => 'invoice.custom_value1',
            25 => 'invoice.custom_value2',
            26 => 'invoice.custom_value3',
            27 => 'invoice.custom_value4',
            28 => 'invoice.custom_surcharge1',
            29 => 'invoice.custom_surcharge2',
            30 => 'invoice.custom_surcharge3',
            31 => 'invoice.custom_surcharge4',
            32 => 'invoice.exchange_rate',
            33 => 'payment.date',
            34 => 'payment.amount',
            35 => 'payment.transaction_reference',
            36 => 'item.quantity',
            37 => 'item.cost',
            38 => 'item.product_key',
            39 => 'item.notes',
            40 => 'item.discount',
            41 => 'item.is_amount_discount',
            42 => 'item.tax_name1',
            43 => 'item.tax_rate1',
            44 => 'item.tax_name2',
            45 => 'item.tax_rate2',
            46 => 'item.tax_name3',
            47 => 'item.tax_rate3',
            48 => 'item.custom_value1',
            49 => 'item.custom_value2',
            50 => 'item.custom_value3',
            51 => 'item.custom_value4',
            52 => 'item.type_id',
            53 => 'client.email',
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
            53 => 'texts.email',
        ];
    }
}
