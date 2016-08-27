<?php namespace App\Http\Requests;

use App\Models\ExpenseCategory;


class ExpenseRequest extends EntityRequest {

    protected $entityType = ENTITY_EXPENSE;

    public function entity()
    {
        $expense = parent::entity();

        // eager load the documents
        if ($expense && ! $expense->relationLoaded('documents')) {
            $expense->load('documents');
        }

        return $expense;
    }

    public function sanitize()
    {
        $input = $this->all();

        if ($this->expense_category_id) {
            $input['expense_category_id'] = ExpenseCategory::getPrivateId($this->expense_category_id);
            $this->replace($input);
        }

        return $this->all();
    }
}
