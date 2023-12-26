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
use Log;

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

class TransactionTransformer implements BankRevenueInterface
{
    use AppSetup;

    public function transform($transactionResponse)
    {
        $data = [];

        if (!array_key_exists('transactions', $transactionResponse) || !array_key_exists('booked', $transactionResponse["transactions"]))
            throw new \Exception('invalid dataset');

        foreach ($transactionResponse["transactions"]["booked"] as $transaction) {
            $data[] = $this->transformTransaction($transaction);
        }

        return $data;
    }

    public function transformTransaction($transaction)
    {

        if (!array_key_exists('transactionId', $transaction) || !array_key_exists('transactionAmount', $transaction))
            throw new \Exception('invalid dataset');

        // description could be in varios places
        $description = '';
        if (array_key_exists('remittanceInformationStructured', $transaction))
            $description = $transaction["remittanceInformationStructured"];
        else if (array_key_exists('remittanceInformationStructuredArray', $transaction))
            $description = implode('\n', $transaction["remittanceInformationStructuredArray"]);
        else if (array_key_exists('remittanceInformationUnstructured', $transaction))
            $description = $transaction["remittanceInformationUnstructured"];
        else if (array_key_exists('remittanceInformationUnstructuredArray', $transaction))
            $description = implode('\n', $transaction["remittanceInformationUnstructuredArray"]);
        else
            Log::warning("Missing description for the following transaction: " . json_encode($transaction));

        // participant
        $participant = array_key_exists('debtorAccount', $transaction) && array_key_exists('iban', $transaction["debtorAccount"]) ?
            $transaction['debtorAccount']['iban'] :
            (array_key_exists('creditorAccount', $transaction) && array_key_exists('iban', $transaction["creditorAccount"]) ?
                $transaction['creditorAccount']['iban'] : null);
        $participant_name = array_key_exists('debtorName', $transaction) ?
            $transaction['debtorName'] :
            (array_key_exists('creditorName', $transaction) ?
                $transaction['creditorName'] : null);

        return [
            'transaction_id' => $transaction["transactionId"],
            'amount' => abs((int) $transaction["transactionAmount"]["amount"]),
            'currency_id' => $this->convertCurrency($transaction["transactionAmount"]["currency"]),
            'category_id' => null, // nordigen has no categories
            'category_type' => array_key_exists('additionalInformation', $transaction) ? $transaction["additionalInformation"] : '', // TODO: institution specific keys like: GUTSCHRIFT, ABSCHLUSS, MONATSABSCHLUSS etc
            'date' => $transaction["bookingDate"],
            'description' => $description,
            'participant' => $participant,
            'participant_name' => $participant_name,
            'base_type' => (int) $transaction["transactionAmount"]["amount"] <= 0 ? 'DEBIT' : 'CREDIT',
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


