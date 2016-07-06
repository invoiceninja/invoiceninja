<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use Auth;
use App\Models\ExpenseCategory;

class ExpenseCategoryRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\ExpenseCategory';
    }

    public function find($filter = null)
    {
        $query = DB::table('expense_categories')
                ->where('expense_categories.account_id', '=', Auth::user()->account_id)
                ->select(
                    'expense_categories.name as category',
                    'expense_categories.public_id',
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

    public function save($input, $category = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ( ! $category) {
            $category = ExpenseCategory::createNew();
        }

        $category->fill($input);
        $category->save();

        return $category;
    }
}
