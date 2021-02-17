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
        ];
    }
}
