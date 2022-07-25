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

use App\Factory\RecurringExpenseFactory;
use App\Models\RecurringExpense;
use App\Utils\Traits\GeneratesCounter;

/**
 * RecurringExpenseRepository.
 */
class RecurringExpenseRepository extends BaseRepository
{
    use GeneratesCounter;

    /**
     * Saves the recurring_expense and its contacts.
     *
     * @param      array  $data    The data
     * @param      \App\Models\RecurringExpense              $recurring_expense  The recurring_expense
     *
     * @return     \App\Models\RecurringExpense|null  recurring_expense Object
     */
    public function save(array $data, RecurringExpense $recurring_expense) : ?RecurringExpense
    {
        $recurring_expense->fill($data);
        $recurring_expense->number = empty($recurring_expense->number) ? $this->getNextRecurringExpenseNumber($recurring_expense) : $recurring_expense->number;
        $recurring_expense->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $recurring_expense);
        }

        return $recurring_expense;
    }

    /**
     * Store recurring_expenses in bulk.
     *
     * @param array $recurring_expense
     * @return \App\Models\RecurringExpense|null
     */
    public function create($recurring_expense): ?RecurringExpense
    {
        return $this->save(
            $recurring_expense,
            RecurringExpenseFactory::create(auth()->user()->company()->id, auth()->user()->id)
        );
    }
}
