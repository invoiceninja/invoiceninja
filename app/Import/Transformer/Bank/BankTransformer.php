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

namespace App\Import\Transformer\Bank;

use App\Import\Transformer\BaseTransformer;

/**
 * Class BankTransformer.
 */
class BankTransformer extends BaseTransformer
{
    /**
     * @param $line_items_data
     *
     * @return bool|array
     */
    public function transform($transaction)
    {
        $now = now();

        $transformed = [
            'bank_integration_id' => $transaction['transaction.bank_integration_id'],
            'transaction_id' => $this->getNumber($transaction, 'transaction.transaction_id'),
            'amount' => abs($this->getFloat($transaction, 'transaction.amount')),
            'currency_id' => $this->getCurrencyByCode($transaction, 'transaction.currency'),
            'account_type' => strlen($this->getString($transaction, 'transaction.account_type')) > 1 ? $this->getString($transaction, 'transaction.account_type') : 'bank',
            'category_id' => $this->getNumber($transaction, 'transaction.category_id') > 0 ? $this->getNumber($transaction, 'transaction.category_id') : null,
            'category_type' => $this->getString($transaction, 'transaction.category_type'),
            'date' => array_key_exists('transaction.date', $transaction) ? $this->parseDate($transaction['transaction.date'])
                : now()->format('Y-m-d'),
            'bank_account_id' => array_key_exists('transaction.bank_account_id', $transaction) ? $transaction['transaction.bank_account_id'] : 0,
            'description' => array_key_exists('transaction.description', $transaction) ? $transaction['transaction.description'] : '',
            'base_type' => $this->calculateType($transaction),
            'created_at' => $now,
            'updated_at' => $now,
            'company_id' => $this->company->id,
            'user_id' => $this->company->owner()->id,
        ];

        return $transformed;
    }


    private function calculateType($transaction)
    {
        if (array_key_exists('transaction.base_type', $transaction) && (($transaction['transaction.base_type'] == 'CREDIT') || strtolower($transaction['transaction.base_type']) == 'deposit')) {
            return 'CREDIT';
        }

        if (array_key_exists('transaction.base_type', $transaction) && (($transaction['transaction.base_type'] == 'DEBIT') || strtolower($transaction['transaction.base_type']) == 'withdrawal')) {
            return 'DEBIT';
        }

        if (array_key_exists('transaction.category_id', $transaction)) {
            return 'DEBIT';
        }

        if (array_key_exists('transaction.category_type', $transaction) && $transaction['transaction.category_type'] == 'Income') {
            return 'CREDIT';
        }

        if (array_key_exists('transaction.category_type', $transaction)) {
            return 'DEBIT';
        }

        if (array_key_exists('transaction.amount', $transaction) && is_numeric($transaction['transaction.amount']) && $transaction['transaction.amount'] > 0) {
            return 'CREDIT';
        }

        return 'DEBIT';
    }
}
