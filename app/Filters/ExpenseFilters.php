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

namespace App\Filters;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * ExpenseFilters.
 */
class ExpenseFilters extends QueryFilters
{
    /**
     * Filter based on search text.
     *
     * @param string query filter
     * @return Builder
     * @deprecated
     */
    public function filter(string $filter = '') : Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return  $this->builder->where(function ($query) use ($filter) {
            $query->where('expenses.public_notes', 'like', '%'.$filter.'%')
                          ->orWhere('expenses.custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('expenses.custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('expenses.custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('expenses.custom_value4', 'like', '%'.$filter.'%');
        });
    }

    /**
     * Filters the list based on the status
     * archived, active, deleted.
     *
     * @param string filter
     * @return Builder
     */
    public function status(string $filter = '') : Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        $table = 'expenses';
        $filters = explode(',', $filter);

        return $this->builder->where(function ($query) use ($filters, $table) {
            $query->whereNull($table.'.id');

            if (in_array(parent::STATUS_ACTIVE, $filters)) {
                $query->orWhereNull($table.'.deleted_at');
            }

            if (in_array(parent::STATUS_ARCHIVED, $filters)) {
                $query->orWhere(function ($query) use ($table) {
                    $query->whereNotNull($table.'.deleted_at');

                    if (! in_array($table, ['users'])) {
                        $query->where($table.'.is_deleted', '=', 0);
                    }
                });
            }

            if (in_array(parent::STATUS_DELETED, $filters)) {
                $query->orWhere($table.'.is_deleted', '=', 1);
            }
        });
    }

    /**
     * Sorts the list based on $sort.
     *
     * @param string sort formatted as column|asc
     * @return Builder
     */
    public function sort(string $sort) : Builder
    {
        $sort_col = explode('|', $sort);

        if (is_array($sort_col) && in_array($sort_col[1], ['asc', 'desc']) && in_array($sort_col[0], ['public_notes', 'date', 'id_number', 'custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'])) {
            return $this->builder->orderBy($sort_col[0], $sort_col[1]);
        }

        return $this->builder;
    }

    /**
     * Returns the base query.
     *
     * @param int company_id
     * @param User $user
     * @return Builder
     * @deprecated
     */
    public function baseQuery(int $company_id, User $user) : Builder
    {
        $query = DB::table('expenses')
            ->join('companies', 'companies.id', '=', 'expenses.company_id')
            ->where('expenses.company_id', '=', $company_id)
            //->whereRaw('(expenses.name != "" or contacts.first_name != "" or contacts.last_name != "" or contacts.email != "")') // filter out buy now invoices
            ->select(
               // DB::raw('COALESCE(expenses.currency_id, companies.currency_id) currency_id'),
                DB::raw('COALESCE(expenses.country_id, companies.country_id) country_id'),
                DB::raw("CONCAT(COALESCE(expense_contacts.first_name, ''), ' ', COALESCE(expense_contacts.last_name, '')) contact"),
                'expenses.id',
                'expenses.private_notes',
                'expenses.custom_value1',
                'expenses.custom_value2',
                'expenses.custom_value3',
                'expenses.custom_value4',
                'expenses.created_at',
                'expenses.created_at as expense_created_at',
                'expenses.deleted_at',
                'expenses.is_deleted',
                'expenses.user_id',
            );

        /*
         * If the user does not have permissions to view all invoices
         * limit the user to only the invoices they have created
         */
        if (Gate::denies('view-list', Expense::class)) {
            $query->where('expenses.user_id', '=', $user->id);
        }

        return $query;
    }

    /**
     * Filters the query by the users company ID.
     *
     * @return Illuminate\Database\Query\Builder
     */
    public function entityFilter()
    {

        //return $this->builder->whereCompanyId(auth()->user()->company()->id);
        return $this->builder->company();
    }
}
