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

class BankTransactionMap
{
    public static function importable()
    {
        return [
            0 => 'transaction.transaction_id',
            1 => 'transaction.amount',
            2 => 'transaction.currency',
            3 => 'transaction.account_type',
            4 => 'transaction.category_id',
            5 => 'transaction.category_type',
            6 => 'transaction.date',
            7 => 'transaction.bank_account_id',
            8 => 'transaction.description',
            9 => 'transaction.base_type',
        ];
    }

    public static function import_keys()
    {
        return [
            0 => 'texts.transaction_id',
            1 => 'texts.amount',
            2 => 'texts.currency',
            3 => 'texts.account_type',
            4 => 'texts.category_id',
            5 => 'texts.category_type',
            6 => 'texts.date',
            7 => 'texts.bank_account_id',
            8 => 'texts.description',
            9 => 'texts.type',
        ];
    }
}
