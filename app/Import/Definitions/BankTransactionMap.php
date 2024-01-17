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

class BankTransactionMap
{
    public static function importable()
    {
        return [
            0 => 'transaction.transaction_id',
            1 => 'transaction.amount',
            2 => 'transaction.currency',
            3 => 'transaction.account_type',
            4 => 'transaction.category',
            5 => 'transaction.category_type',
            6 => 'transaction.date',
            7 => 'transaction.bank_account',
            8 => 'transaction.description',
            9 => 'transaction.base_type',
            10 => 'transaction.payment_type_Credit',
            11 => 'transaction.payment_type_Debit',
            12 => 'transaction.participant',
            13 => 'transaction.participant_name',
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
            10 => 'transaction.credit',
            11 => 'transaction.debit',
            12 => 'transaction.participant',
            13 => 'transaction.participant_name',
        ];
    }
}
