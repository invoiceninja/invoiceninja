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

namespace App\Repositories;

use App\Factory\ExpenseFactory;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Expense;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Carbon;

/**
 * ExpenseRepository.
 */
class ExpenseRepository extends BaseRepository
{
    use GeneratesCounter;

    /**
     * Saves the expense and its contacts.
     *
     * @param      array  $data    The data
     * @param      \App\Models\Expense              $expense  The expense
     *
     * @return     \App\Models\Expense|null  expense Object
     */
    public function save(array $data, Expense $expense) : ?Expense
    {
        $expense->fill($data);

        if (! $expense->id) {
            $expense = $this->processExchangeRates($data, $expense);
        }

        $expense->number = empty($expense->number) ? $this->getNextExpenseNumber($expense) : $expense->number;
        $expense->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $expense);
        }

        return $expense;
    }

    /**
     * Store expenses in bulk.
     *
     * @param array $expense
     * @return \App\Models\Expense|null
     */
    public function create($expense): ?Expense
    {
        return $this->save(
            $expense,
            ExpenseFactory::create(auth()->user()->company()->id, auth()->user()->id)
        );
    }

    public function processExchangeRates($data, $expense)
    {
        if (array_key_exists('exchange_rate', $data) && isset($data['exchange_rate']) && $data['exchange_rate'] != 1) {
            return $expense;
        }

        $expense_currency = $data['currency_id'];
        $company_currency = $expense->company->settings->currency_id;

        if ($company_currency != $expense_currency) {
            $exchange_rate = new CurrencyApi();

            $expense->exchange_rate = $exchange_rate->exchangeRate($expense_currency, $company_currency, Carbon::parse($expense->date));

            return $expense;
        }

        return $expense;
    }
}
