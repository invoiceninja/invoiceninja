<?php

namespace App\Ninja\Repositories;

use App\Models\ExpenseCategory;
use Auth;
use DB;

class ExpenseCategoryRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\ExpenseCategory';
    }

    public function all()
    {
        return ExpenseCategory::scope()->get();
    }

    public function find($filter = null)
    {
        $query = DB::table('expense_categories')
                ->where('expense_categories.account_id', '=', Auth::user()->account_id)
                ->select(
                    'expense_categories.name as category',
                    'expense_categories.public_id',
                    'expense_categories.user_id',
                    'expense_categories.deleted_at',
                    'expense_categories.is_deleted'
                );

        $this->applyFilters($query, ENTITY_EXPENSE_CATEGORY);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('expense_categories.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($input, $category = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if (! $category) {
            $category = ExpenseCategory::createNew();
        }

        $category->fill($input);
        $category->save();

        return $category;
    }
}
