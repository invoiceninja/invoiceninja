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

namespace App\Helpers\Bank\Yodlee\Transformer;

use App\Helpers\Bank\BankRevenueInterface;
 
/**
"date": "string",
"sourceId": "string",
"symbol": "string",
"cusipNumber": "string",
"highLevelCategoryId": 0,
"detailCategoryId": 0,
"description": {},
"memo": "string",
"settleDate": "string",
"type": "string",
"intermediary": [],
"baseType": "CREDIT",
"categorySource": "SYSTEM",
"principal": {},
"lastUpdated": "string",
"interest": {},
"price": {},
"commission": {},
"id": 0,
"merchantType": "string",
"amount": {
"amount": 0,
"convertedAmount": 0,
"currency": "USD",
"convertedCurrency": "USD"
},
"checkNumber": "string",
"isPhysical": true,
"quantity": 0,
"valoren": "string",
"isManual": true,
"merchant": {
"website": "string",
"address": {},
"contact": {},
"categoryLabel": [],
"coordinates": {},
"name": "string",
"id": "string",
"source": "YODLEE",
"logoURL": "string"
},
"sedol": "string",
"transactionDate": "string",
"categoryType": "TRANSFER",
"accountId": 0,
"createdDate": "string",
"sourceType": "AGGREGATED",
"CONTAINER": "bank",
"postDate": "string",
"parentCategoryId": 0,
"subType": "OVERDRAFT_CHARGE",
"category": "string",
"runningBalance": {},
"categoryId": 0,
"holdingDescription": "string",
"isin": "string",
"status": "POSTED"
 
(
[CONTAINER] => bank
[id] => 103953585
[amount] => stdClass Object
    (
        [amount] => 480.66
        [currency] => USD
    )

[categoryType] => UNCATEGORIZE
[categoryId] => 1
[category] => Uncategorized
[categorySource] => SYSTEM
[highLevelCategoryId] => 10000017
[createdDate] => 2022-08-04T21:50:17Z
[lastUpdated] => 2022-08-04T21:50:17Z
[description] => stdClass Object
    (
        [original] => CHEROKEE NATION TAX TA TAHLEQUAH OK
    )

[isManual] => 
[sourceType] => AGGREGATED
[date] => 2022-08-03
[transactionDate] => 2022-08-03
[postDate] => 2022-08-03
[status] => POSTED
[accountId] => 12331794
[runningBalance] => stdClass Object
    (
        [amount] => 480.66
        [currency] => USD
    )

[checkNumber] => 998
)
*/

class IncomeTransformer implements BankRevenueInterface
{

    public function transform($transaction)
    {

        $data = [];

        if(!property_exists($transaction, 'transaction'))
            return $data;

        foreach($transaction->transaction as $transaction)
        {
            $data[] = $this->transformTransaction($transaction);
        }

        return $data;
    }

    public function transformTransaction($transaction)
    {
    
        return [
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount->amount,
            'currency_code' => $transaction->amount->currency,
            'account_type' => $transaction->CONTAINER,
            'category_id' => $transaction->categoryId,
            'category_type' => $transaction->categoryType,
            'date' => $transaction->date,
            'bank_account_id' => $transaction->accountId,
            'description' => $transaction->description->original,
        ];
    }

}


