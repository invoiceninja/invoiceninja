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

    public function __construct()
    {
    }

    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {
        return Expense::class;
    }

    /**
     * Saves the expense and its contacts.
     *
     * @param      array                           $data    The data
     * @param      \App\Models\expense              $expense  The expense
     *
     * @return     expense|null  expense Object
     */
    public function save(array $data, Expense $expense) : ?Expense
    {
        $expense->fill($data);
        $expense->number = empty($expense->number) ? $this->getNextExpenseNumber($expense) : $expense->number;

        $expense->save();


        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $expense);
        }

        // if ($expense->id_number == "" || !$expense->id_number) {
        //     $expense->id_number = $this->getNextExpenseNumber($expense);
        // } //todo write tests for this and make sure that custom expense numbers also works as expected from here

        return $expense;
    }

    /**
     * Store expenses in bulk.
     *
     * @param array $expense
     * @return expense|null
     */
    public function create($expense): ?Expense
    {
        return $this->save(
            $expense,
            ExpenseFactory::create(auth()->user()->company()->id, auth()->user()->id)
        );
    }
}
