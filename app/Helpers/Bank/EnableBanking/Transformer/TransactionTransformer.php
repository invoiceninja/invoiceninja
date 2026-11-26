<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\EnableBanking\Transformer;

use App\Helpers\Bank\BankRevenueInterface;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DateFormat;
use App\Models\Timezone;
use Carbon\Carbon;
use App\Utils\Traits\AppSetup;
use Illuminate\Support\Facades\Cache;
use Log;

/**
 * @see https://enablebanking.com/docs/api/reference/#get-account-transactions
{
    "transactions": [
    {
        "entry_reference": "5561990681",
      "merchant_category_code": "5511",
      "transaction_amount": {
        "currency": "EUR",
        "amount": "1.23"
      },
      "creditor": {
        "name": "MyPreferredAisp",
        "postal_address": {
            "address_line": [
                "Mr Asko Teirila PO Box 511",
                "39140 AKDENMAA FINLAND"
            ],
          "address_type": "Business",
          "building_number": "4",
          "country": "FI",
          "country_sub_division": "Uusimaa",
          "department": "Department of resources",
          "post_code": "00123",
          "street_name": "Vasavagen",
          "sub_department": "Sub Department of resources",
          "town_name": "Helsinki"
        }
      },
      "creditor_account": {
        "iban": "FI0455231152453547"
      },
      "creditor_agent": {
        "bic_fi": "string",
        "clearing_system_member_id": {
            "clearing_system_id": "NZNCC",
          "member_id": 20368
        },
        "name": "string"
      },
      "debtor": {
        "name": "MyPreferredAisp",
        "postal_address": {
            "address_line": [
                "Mr Asko Teirila PO Box 511",
                "39140 AKDENMAA FINLAND"
            ],
          "address_type": "Business",
          "building_number": "4",
          "country": "FI",
          "country_sub_division": "Uusimaa",
          "department": "Department of resources",
          "post_code": "00123",
          "street_name": "Vasavagen",
          "sub_department": "Sub Department of resources",
          "town_name": "Helsinki"
        }
      },
      "debtor_account": {
        "iban": "FI0455231152453547"
      },
      "debtor_agent": {
        "bic_fi": "string",
        "clearing_system_member_id": {
            "clearing_system_id": "NZNCC",
          "member_id": 20368
        },
        "name": "string"
      },
      "bank_transaction_code": {
        "description": "Utlandsbetalning",
        "code": "12",
        "sub_code": "32"
      },
      "credit_debit_indicator": "CRDT",
      "status": "BOOK",
      "booking_date": "2020-01-03",
      "value_date": "2020-01-02",
      "transaction_date": "2020-01-01",
      "balance_after_transaction": {
        "currency": "EUR",
        "amount": "1.23"
      },
      "reference_number": "RF07850352502356628678117",
      "reference_number_schema": "SEBG",
      "remittance_information": [
        "RF07850352502356628678117",
        "Gift for Alex"
    ],
      "debtor_account_additional_identification": {
        "identification": "12345678",
        "scheme_name": "CPAN"
      },
      "creditor_account_additional_identification": {
        "identification": "12345678",
        "scheme_name": "BBAN"
      },
      "exchange_rate": {
        "unit_currency": "EUR",
        "exchange_rate": "string",
        "rate_type": "SPOT",
        "contract_identification": "string",
        "instructed_amount": {
            "currency": "EUR",
          "amount": "1.23"
        }
      },
      "note": "string",
      "transaction_id": "string"
    }
  ],
  "continuation_key": "string"
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

        if (!array_key_exists(
            'transactions',
            $transactionResponse,
        )) {
            throw new \Exception('invalid dataset');
        }

        foreach ($transactionResponse["transactions"] as $transaction) {
            if ($transaction['status'] !== 'BOOK') {
                continue;
            }

            $data[] = $this->transformTransaction($transaction);
        }

        return $data;
    }

    public function transformTransaction($transaction)
    {
        if (array_key_exists('entry_reference', $transaction)) {
            $transactionId = $transaction['entry_reference'];
        } else {
            nlog('Invalid Input for enablebanking transaction transformer: ' . $transaction);
            throw new \Exception('invalid dataset: missing entry_reference - Please report this error to the developer');
        }

        $amount = (float) $transaction['transaction_amount']['amount'] ?? 0;
        $base_type = ($transaction['credit_debit_indicator'] ?? '') === 'CRDT' ? 'CREDIT' : 'DEBIT';
        $description = $transaction['note'] ?? null;

        // participant data
        $participant = array_key_exists('debtor_account', $transaction) && is_array($transaction['debtor_account']) && array_key_exists('iban', $transaction['debtor_account']) ?
            $transaction['debtor_account']['iban'] :
            (array_key_exists('creditor_account', $transaction) && is_array($transaction['creditor_account']) && array_key_exists('iban', $transaction['creditor_account']) ?
                $transaction['creditor_account']['iban'] : null);
        $participant_name = array_key_exists('debtor', $transaction) && is_array($transaction['debtor']) && array_key_exists('name', $transaction['debtor']) ?
            $transaction['debtor']['name'] :
            (array_key_exists('creditor', $transaction) && is_array($transaction['creditor']) && array_key_exists('name', $transaction['creditor']) ?
                $transaction['creditor']['name'] : null);

        if (!$description)
        {
            $description = ctrans('texts.enablebanking_transaction_description',
                [
                    'type' => $base_type == 'DEBIT' ? ctrans('texts.payment_type_Debit') : ctrans('texts.payment_type_Credit'),
                    'participant' => $participant_name
                ]);
        }

        // enrich description with currencyExchange informations
        if ($transaction['exchange_rate']) {
            $targetAmount = round($amount * (float) ($transaction['exchange_rate']['exchange_rate'] ?? 1), 2);
            $description .= '\n' . ctrans('texts.exchange_rate') . ' : ' . $amount . " " . ($transaction['exchange_rate']['instructed_amount']['currency'] ?? '?') . " = " . $targetAmount . " " . ($transaction['exchange_rate']['unit_currency'] ?? '?') . ")";
        }

        $data = [
            'transaction_id' => 0,
            'enablebanking_transaction_id' => $transactionId,
            'amount' => abs($amount),
            'currency_id' => $this->convertCurrency($transaction['transaction_amount']['currency']),
            'category_id' => null,
            'category_type' => $transaction['note'] ?? '',
            'date' => $transaction['booking_date'],
            'description' => $description,
            'participant' => $participant,
            'participant_name' => $participant_name,
            'base_type' => $base_type,
        ];

        return $data;
    }

    private function convertCurrency(string $code)
    {
        $currencies = app('currencies');

        $currency = $currencies->first(function ($item) use ($code) {
            /** @var Currency $item */
            return $item->code == $code;
        });

        /** @var Currency $currency */
        return $currency ? $currency->id : 1;
    }
}