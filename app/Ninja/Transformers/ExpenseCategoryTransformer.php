<?php

namespace App\Ninja\Transformers;

use App\Models\ExpenseCategory;

/**
 * @SWG\Definition(definition="ExpenseCategory", @SWG\Xml(name="ExpenseCategory"))
 */
class ExpenseCategoryTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="name", type="string", example="Sample")
     * @SWG\Property(property="updated_at", type="string", format="date-time", example="2016-01-01 12:10:00", readOnly=true)
     * @SWG\Property(property="archived_at", type="string", format="date-time", example="2016-01-01 12:10:00", readOnly=true)
     */
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
