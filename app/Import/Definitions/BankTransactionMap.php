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
            0 => 'bank.transaction_id',
            1 => 'bank.amount',
            2 => 'bank.currency',
            3 => 'bank.account_type',
            4 => 'bank.category_id',
            5 => 'bank.category_type',
            6 => 'bank.date',
            7 => 'bank.bank_account_id',
            8 => 'bank.description',
            9 => 'bank.base_type',
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