<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\Nordigen\Transformer;

use App\Helpers\Bank\BankRevenueInterface;
use App\Models\BankIntegration;
use App\Utils\Traits\AppSetup;
use Illuminate\Support\Facades\Cache;

/**
{
  "transactions": {
    "booked": [
      {
        "transactionId": "string",
        "debtorName": "string",
        "debtorAccount": {
          "iban": "string"
        },
        "transactionAmount": {
          "currency": "string",
          "amount": "328.18"
        },
        "bankTransactionCode": "string",
        "bookingDate": "date",
        "valueDate": "date",
        "remittanceInformationUnstructured": "string"
      },
      {
        "transactionId": "string",
        "transactionAmount": {
          "currency": "string",
          "amount": "947.26"
        },
        "bankTransactionCode": "string",
        "bookingDate": "date",
        "valueDate": "date",
        "remittanceInformationUnstructured": "string"
      }
    ],
    "pending": [
      {
        "transactionAmount": {
          "currency": "string",
          "amount": "99.20"
        },
        "valueDate": "date",
        "remittanceInformationUnstructured": "string"
      }
    ]
  }
}
*/

class IncomeTransformer implements BankRevenueInterface
{
    use AppSetup;

    public function transform($transaction)
    {

        $data = [];

        if (!property_exists($transaction, 'transactions') || !property_exists($transaction->transactions, 'booked'))
            throw new \Exception('invalid dataset');

        foreach ($transaction->transactions->booked as $transaction) {
            $data[] = $this->transformTransaction($transaction);
        }

        return $data;
    }

    public function transformTransaction($transaction)
    {

        if (!property_exists($transaction, 'transactionId') || !property_exists($transaction, 'transactionAmount') || !property_exists($transaction, 'balances') || !property_exists($transaction, 'institution'))
            throw new \Exception('invalid dataset');

        return [
            'transaction_id' => $transaction->transactionId,
            'amount' => abs($transaction->transactionAmount->amount),
            'currency_id' => $this->convertCurrency($transaction->transactionAmount->currency),
            'category_id' => $transaction->highLevelCategoryId, // TODO
            'category_type' => $transaction->categoryType, // TODO
            'date' => $transaction->bookingDate,
            'description' => $transaction->remittanceInformationUnstructured,
            'base_type' => $transaction->transactionAmount->amount > 0 ? 'DEBIT' : 'CREDIT',
        ];

    }

    private function convertCurrency(string $code)
    {

        $currencies = Cache::get('currencies');

        if (!$currencies) {
            $this->buildCache(true);
        }

        $currency = $currencies->filter(function ($item) use ($code) {
            return $item->code == $code;
        })->first();

        if ($currency)
            return $currency->id;

        return 1;

    }

}


