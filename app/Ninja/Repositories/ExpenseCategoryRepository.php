<?php

namespace App\Ninja\Repositories;

use DB;
use Auth;
use App\Models\ExpenseCategory;

class ExpenseCategoryRepository extends BaseRepository
{
    /**
     * @return string
     */
    public function getClassName()
    {
        return 'App\Models\ExpenseCategory';
    }

    /**
     * @param null $filter
     *
     * @return $this
     */
    public function find($filter = null)
    {
        $query = DB::table('expense_categories')
                ->where('expense_categories.account_id', '=', Auth::user()->account_id)
                ->select(
                    'expense_categories.name as category',
                    'expense_categories.public_id',
                    'expense_categories.user_id',
                    'expense_categories.deleted_at'
                );

        if (!\Session::get('show_trash:expense_category')) {
            $query->where('expense_categories.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('expense_categories.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    /**
     * @param array $input
     * @param ExpenseCategory $category
     *
     * @return ExpenseCategory|mixed
     */
    public function save(array $input, ExpenseCategory $category = false)
    {
        if ( ! $category) {
            $category = ExpenseCategory::createNew();
        }

        $category->fill($input);
        $category->save();

        return $category;
    }
}
