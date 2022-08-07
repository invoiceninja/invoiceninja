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

namespace App\Http\ValidationRules\Expense;

use App\Models\Expense;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueExpenseNumberRule.
 */
class UniqueExpenseNumberRule implements Rule
{
    public $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkIfExpenseNumberUnique(); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.expense_number_taken');
    }

    /**
     * @return bool
     */
    private function checkIfExpenseNumberUnique() : bool
    {
        if (empty($this->input['number'])) {
            return true;
        }

        $expense = Expense::query()
                          ->where('number', $this->input['number'])
                          ->withTrashed();

        return $expense->exists();
    }
}
