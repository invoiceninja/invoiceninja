<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use App\Models\Vendor;

class ExpenseRequest extends EntityRequest
{
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

        // check if we're creating a new expense category
        if ($this->expense_category_id == '-1') {
            $data = [
                'name' => trim($this->expense_category_name)
            ];
            if (ExpenseCategory::validate($data) === true) {
                $category = app('App\Ninja\Repositories\ExpenseCategoryRepository')->save($data);
                $input['expense_category_id'] = $category->id;
            } else {
                $input['expense_category_id'] = null;
            }
        } elseif ($this->expense_category_id) {
            $input['expense_category_id'] = ExpenseCategory::getPrivateId($this->expense_category_id);
        }

        // check if we're creating a new vendor
        if ($this->vendor_id == '-1') {
            $data = [
                'name' => trim($this->vendor_name)
            ];
            if (Vendor::validate($data) === true) {
                $vendor = app('App\Ninja\Repositories\VendorRepository')->save($data);
                // TODO change to private id once service is refactored
                $input['vendor_id'] = $vendor->public_id;
            } else {
                $input['vendor_id'] = null;
            }
        }

        $this->replace($input);

        return $this->all();
    }
}
