<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Factory\ExpenseFactory;
use App\Models\Expense;
use App\Repositories\VSendorContactRepository;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

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
     * @return     \App\Models\Expense|Null  expense Object
     */
    public function save(array $data, Expense $expense) : ?Expense
    {
        $expense->fill($data);
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
}
