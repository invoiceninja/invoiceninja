<?php namespace App\Ninja\Transformers;

use App\Models\ExpenseCategory;

class ExpenseCategoryTransformer extends EntityTransformer
{

    public function transform(ExpenseCategory $expenseCategory)
    {
        return array_merge($this->getDefaults($expenseCategory), [
            'id' => (int) $expenseCategory->public_id,
            'name' => $expenseCategory->name,
            'updated_at' => $this->getTimestamp($expenseCategory->updated_at),
            'archived_at' => $this->getTimestamp($expenseCategory->deleted_at),
        ]);
    }
}