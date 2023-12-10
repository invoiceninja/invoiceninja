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

class ExpenseMap
{
    public static function importable()
    {
        return [
            0 => 'expense.vendor',
            1 => 'expense.client',
            2 => 'expense.project',
            3 => 'expense.category',
            4 => 'expense.amount',
            5 => 'expense.currency',
            6 => 'expense.date',
            7 => 'expense.payment_type',
            8 => 'expense.payment_date',
            9 => 'expense.transaction_reference',
            10 => 'expense.public_notes',
            11 => 'expense.private_notes',
            12 => 'expense.tax_name1',
            13 => 'expense.tax_rate1',
            14 => 'expense.tax_name2',
            15 => 'expense.tax_rate2',
            16 => 'expense.tax_name3',
            17 => 'expense.tax_rate3',
            18 => 'expense.uses_inclusive_taxes',
            19 => 'expense.payment_date',
            20 => 'expense.custom_value1',
            21 => 'expense.custom_value2',
            22 => 'expense.custom_value3',
            23 => 'expense.custom_value4',

        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.vendor',
            1 => 'texts.client',
            2 => 'texts.project',
            3 => 'texts.category',
            4 => 'texts.amount',
            5 => 'texts.currency',
            6 => 'texts.date',
            7 => 'texts.payment_type',
            8 => 'texts.payment_date',
            9 => 'texts.transaction_reference',
            10 => 'texts.public_notes',
            11 => 'texts.private_notes',
            12 => 'texts.tax_name1',
            13 => 'texts.tax_rate1',
            14 => 'texts.tax_name2',
            15 => 'texts.tax_rate2',
            16 => 'texts.tax_name3',
            17 => 'texts.tax_rate3',
            18 => 'texts.uses_inclusive_taxes',
            19 => 'texts.payment_date',
            20 => 'texts.custom_value1',
            21 => 'texts.custom_value2',
            22 => 'texts.custom_value3',
            23 => 'texts.custom_value4',

        ];
    }
}
