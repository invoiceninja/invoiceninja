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

    /**
     * Transform an EnableBanking transactions response (or a list of raw transactions).
     *
     * Only booked transactions (status BOOK) are kept.
     */
    public function transform($transactionResponse)
    {
        $transactions = data_get($transactionResponse, 'transactions');

        if (!is_array($transactions)) {
            throw new \Exception('invalid dataset');
        }

        $data = [];

        foreach ($transactions as $transaction) {
            if (data_get($transaction, 'status') !== 'BOOK') {
                continue;
            }

            $transformed = $this->transformTransaction($transaction);

            if ($transformed) {
                $data[] = $transformed;
            }
        }

        return $data;
    }

    /**
     * Transform a single EnableBanking transaction.
     *
     * Only transaction_amount, credit_debit_indicator and status are required by the
     * EnableBanking API; every other field is optional and read with data_get().
     *
     * @see https://enablebanking.com/docs/api/reference/#transaction
     */
    public function transformTransaction($transaction): ?array
    {
        $date = data_get($transaction, 'booking_date')
            ?? data_get($transaction, 'value_date')
            ?? data_get($transaction, 'transaction_date');

        if (!$date) {
            nlog('EnableBanking: skipping transaction without date: ' . json_encode($transaction));

            return null;
        }

        $amount = (float) data_get($transaction, 'transaction_amount.amount', 0);
        $currency = data_get($transaction, 'transaction_amount.currency', 'EUR');
        $base_type = data_get($transaction, 'credit_debit_indicator') === 'CRDT' ? 'CREDIT' : 'DEBIT';
        $note = data_get($transaction, 'note');
        $description = $this->description($transaction);

        // participant data
        $participant = data_get($transaction, 'debtor_account.iban') ?? data_get($transaction, 'creditor_account.iban');
        $participant_name = data_get($transaction, 'debtor.name') ?? data_get($transaction, 'creditor.name');

        if (!$description) {
            $description = ctrans('texts.enablebanking_transaction_description', [
                'type' => $base_type == 'DEBIT' ? ctrans('texts.payment_type_Debit') : ctrans('texts.payment_type_Credit'),
                'participant' => $participant_name,
            ]);
        }

        // enrich description with currency exchange information
        $exchange_rate = data_get($transaction, 'exchange_rate.exchange_rate');

        if ($exchange_rate) {
            $target_amount = round($amount * (float) $exchange_rate, 2);
            $description .= "\n" . ctrans('texts.exchange_rate') . ' : ' . $amount . ' ' . data_get($transaction, 'exchange_rate.instructed_amount.currency', '?') . ' = ' . $target_amount . ' ' . data_get($transaction, 'exchange_rate.unit_currency', '?');
        }

        return [
            'transaction_id' => 0,
            'nordigen_transaction_id' => $this->transactionId($transaction, $date),
            'amount' => abs($amount),
            'currency_id' => $this->convertCurrency($currency),
            'category_id' => null,
            'category_type' => $note ?? '',
            'date' => $date,
            'description' => $description,
            'participant' => $participant,
            'participant_name' => $participant_name,
            'base_type' => $base_type,
        ];
    }

    /**
     * Build the description from all the fields that can carry it.
     *
     * Banks spread the payment details over remittance_information (a list of lines), the
     * structured reference_number and the PSU note. Combine the non-empty, unique values.
     */
    private function description(array $transaction): string
    {
        $remittance = data_get($transaction, 'remittance_information') ?? [];

        $lines = collect(is_array($remittance) ? $remittance : [$remittance])
            ->filter(fn ($line) => is_scalar($line) && trim((string) $line) !== '')
            ->map(fn ($line) => trim((string) $line));

        // reference_number is often repeated inside a remittance line; only add it when it is not
        $reference = trim((string) data_get($transaction, 'reference_number', ''));

        if ($reference !== '' && !$lines->contains(fn ($line) => str_contains($line, $reference))) {
            $lines->push($reference);
        }

        $note = trim((string) data_get($transaction, 'note', ''));

        if ($note !== '') {
            $lines->push($note);
        }

        return $lines->unique()->implode("\n");
    }

    /**
     * Determine a stable identifier for the transaction (used for de-duplication).
     *
     * entry_reference and transaction_id are both optional in the EnableBanking API. When
     * neither is present, fall back to a deterministic hash of the immutable transaction data.
     */
    private function transactionId(array $transaction, string $date): string
    {
        $id = data_get($transaction, 'entry_reference') ?? data_get($transaction, 'transaction_id');

        if ($id) {
            return (string) $id;
        }

        return 'eb_' . sha1(json_encode([
            $date,
            data_get($transaction, 'transaction_amount.amount'),
            data_get($transaction, 'transaction_amount.currency'),
            data_get($transaction, 'credit_debit_indicator'),
            data_get($transaction, 'remittance_information'),
            data_get($transaction, 'reference_number'),
            data_get($transaction, 'debtor_account.iban'),
            data_get($transaction, 'creditor_account.iban'),
        ]));
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
