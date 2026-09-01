<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\Nordigen\Transformer;

use App\Helpers\Bank\BankRevenueInterface;
use App\Models\Company;
use App\Models\DateFormat;
use App\Models\Timezone;
use Carbon\Carbon;
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

    private Company $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    public function transform($transactionResponse)
    {
        $data = [];

        if (!array_key_exists('transactions', $transactionResponse) || !array_key_exists('booked', $transactionResponse["transactions"])) {
            throw new \Exception('invalid dataset');
        }

        foreach ($transactionResponse["transactions"]["booked"] as $transaction) {
            $data[] = $this->transformTransaction($transaction);
        }
        return $data;
    }

    public function transformTransaction($transaction)
    {
        // depending on institution, the result can be different, so we load the first available unique id
        $transactionId = '';
        if (array_key_exists('transactionId', $transaction)) {
            $transactionId = $transaction["transactionId"];
        } elseif (array_key_exists('internalTransactionId', $transaction)) {
            $transactionId = $transaction["internalTransactionId"];
        } else {
            nlog('Invalid Input for nordigen transaction transformer: ' . $transaction);
            throw new \Exception('invalid dataset: missing transactionId - Please report this error to the developer');
        }

        $amount = (float) $transaction["transactionAmount"]["amount"];
        $base_type = $amount < 0 ? 'DEBIT' : 'CREDIT';

        // description could be in various places
        $description = '';
        if (array_key_exists('remittanceInformationStructured', $transaction)) {
            $description = $transaction["remittanceInformationStructured"];
        } elseif (array_key_exists('remittanceInformationStructuredArray', $transaction)) {
            $remittanceInformationStructuredArray = $transaction["remittanceInformationStructuredArray"];
            if (array_key_exists('rawTransactionDescription', $remittanceInformationStructuredArray)) {
                $description = $remittanceInformationStructuredArray["rawTransactionDescription"];
            } else {
                $description = implode('\n', $transaction["remittanceInformationStructuredArray"]);
            }
        } elseif (array_key_exists('remittanceInformationUnstructured', $transaction)) {
            $description = $transaction["remittanceInformationUnstructured"];
        } elseif (array_key_exists('remittanceInformationUnstructuredArray', $transaction)) {
            $description = implode('\n', $transaction["remittanceInformationUnstructuredArray"]);
        } else {
            Log::warning("Missing description for the following transaction: " . json_encode($transaction));
        }

        // enrich description with currencyExchange informations
        if (isset($transaction['currencyExchange'])) {
            foreach ($transaction["currencyExchange"] as $exchangeRate) {
                $targetAmount = round($amount * (float) ($exchangeRate["exchangeRate"] ?? 1), 2);
                $description .= '\n' . ctrans('texts.exchange_rate') . ' : ' . $amount . " " . ($exchangeRate["sourceCurrency"] ?? '?') . " = " . $targetAmount . " " . ($exchangeRate["targetCurrency"] ?? '?') . " (" . (isset($exchangeRate["quotationDate"]) ? $this->formatDate($exchangeRate["quotationDate"]) : '?') . ")";
            }
        }

        // CREDIT: counterparty is the debtor (payer). DEBIT: counterparty is the creditor (payee).
        // Banks often include both sides; never prefer debtor on outgoing payments.
        $prefer_debtor = $base_type === 'CREDIT';
        $participant = $this->ibanFromAccount($transaction, $prefer_debtor ? 'debtorAccount' : 'creditorAccount')
            ?? $this->ibanFromAccount($transaction, $prefer_debtor ? 'creditorAccount' : 'debtorAccount');
        $participant_name = $this->nameFromTransaction($transaction, $prefer_debtor ? 'debtorName' : 'creditorName')
            ?? $this->nameFromTransaction($transaction, $prefer_debtor ? 'creditorName' : 'debtorName');

        $data = [
            'transaction_id' => 0,
            'nordigen_transaction_id' => $transactionId,
            'amount' => abs($amount),
            'currency_id' => $this->convertCurrency($transaction["transactionAmount"]["currency"]),
            'category_id' => null,
            'category_type' => array_key_exists('additionalInformation', $transaction) ? $transaction["additionalInformation"] : '',
            'date' => $transaction["bookingDate"],
            'description' => $description,
            'participant' => $participant,
            'participant_name' => $participant_name,
            'base_type' => $base_type,
        ];

        // $data['currency_code'] = $this->makeHash($data);

        return $data;
    }

    // private function makeHash($data)
    // {
    //     return hash('sha1', $data['amount'].$data['date'].$data['description'].$data['participant'].$data['participant_name'].$data['base_type']);
    // }

    private function ibanFromAccount(array $transaction, string $account_key): ?string
    {
        if (! array_key_exists($account_key, $transaction) || ! is_array($transaction[$account_key]) || ! array_key_exists('iban', $transaction[$account_key])) {
            return null;
        }

        return $transaction[$account_key]['iban'];
    }

    private function nameFromTransaction(array $transaction, string $name_key): ?string
    {
        if (! array_key_exists($name_key, $transaction)) {
            return null;
        }

        return $transaction[$name_key];
    }

    private function convertCurrency(string $code)
    {

        $currencies = app('currencies');

        $currency = $currencies->first(function ($item) use ($code) {
            /** @var \App\Models\Currency $item */
            return $item->code == $code;
        });

        /** @var \App\Models\Currency $currency */
        return $currency ? $currency->id : 1; //@phpstan-ignore-line

    }

    private function formatDate(string $input)
    {
        $timezone = Timezone::find($this->company->settings->timezone_id);
        $timezone_name = 'America/New_York';

        if ($timezone) {
            $timezone_name = $timezone->name;
        }

        $date_format_default = 'Y-m-d';

        $date_format = DateFormat::find($this->company->settings->date_format_id);

        if ($date_format) {
            $date_format_default = $date_format->format;
        }

        try {
            return Carbon::createFromFormat("d-m-Y", $input)->setTimezone($timezone_name)->format($date_format_default);
        } catch (\Exception $e) {
            return $input;
        }
    }

}
