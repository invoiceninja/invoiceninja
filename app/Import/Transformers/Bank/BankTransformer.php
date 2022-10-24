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

namespace App\Import\Transformers\Bank;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use App\Models\BankTransaction;
use App\Utils\Number;

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
            // 'bank_integration_id' => $this->bank_integration->id,
            'transaction_id' => $this->getNumber($transaction,'bank.transaction_id'),
            'amount' => $this->getFloat($transaction, 'bank.amount'),
            'currency_id' => $this->getCurrencyByCode($transaction, 'bank.currency'),
            'account_type' => array_key_exists('account_type', $transaction) ? $transaction['account_type'] : 'bank',
            'category_id' => array_key_exists('category_id', $transaction) ? $transaction['category_id'] : null,
            'category_type' => array_key_exists('category_type', $transaction) ? $transaction['category_type'] : '',
            'date' => array_key_exists('date', $transaction) ? date('Y-m-d', strtotime(str_replace("/","-",$transaction['date'])))
                : now()->format('Y-m-d'),
            'bank_account_id' => array_key_exists('bank_account_id', $transaction) ? $transaction['bank_account_id'] : 0,
            'description' => array_key_exists('description', $transaction)? $transaction['description'] : '',
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

    	if(array_key_exists('base_type', $transaction) && $transaction['base_type'] == 'CREDIT')
    		return 'CREDIT';

    	if(array_key_exists('base_type', $transaction) && $transaction['base_type'] == 'DEBIT')
    		return 'DEBIT';

    	if(array_key_exists('category_id',$transaction))
    		return 'DEBIT';

    	if(array_key_exists('category_type', $transaction) && $transaction['category_type'] == 'Income')
    		return 'CREDIT';

    	if(array_key_exists('category_type', $transaction))
    		return 'DEBIT';

    	if(array_key_exists('amount', $transaction) && is_numeric($transaction['amount']) && $transaction['amount'] > 0)
    		return 'CREDIT';

    	return 'DEBIT';

    }

}