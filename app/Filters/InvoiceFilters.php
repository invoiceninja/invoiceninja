<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Filters;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * InvoiceFilters
 */
class InvoiceFilters extends QueryFilters
{


    /**
     * Filter based on search text
     * 
     * @param  string query filter
     * @return Illuminate\Database\Query\Builder
     * @deprecated
     *     
     */
    public function filter(string $filter = '') : Builder
    {
        if(strlen($filter) == 0)
            return $this->builder;

        return  $this->builder->where(function ($query) use ($filter) {
                    $query->where('invoices.custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.custom_value2', 'like' , '%'.$filter.'%')
                          ->orWhere('invoices.custom_value3', 'like' , '%'.$filter.'%')
                          ->orWhere('invoices.custom_value4', 'like' , '%'.$filter.'%');
                });
    }

    /**
     * Filters the list based on the status
     * archived, active, deleted
     * 
     * @param  string filter
     * @return Illuminate\Database\Query\Builder
     */
    public function status(string $filter = '') : Builder
    {
        if(strlen($filter) == 0)
            return $this->builder;

        $table = 'invoices';
        $filters = explode(',', $filter);

        return $this->builder->where(function ($query) use ($filters, $table) {
            $query->whereNull($table . '.id');

            if (in_array(parent::STATUS_ACTIVE, $filters)) {
                $query->orWhereNull($table . '.deleted_at');
            }

            if (in_array(parent::STATUS_ARCHIVED, $filters)) {
                $query->orWhere(function ($query) use ($table) {
                    $query->whereNotNull($table . '.deleted_at');

                    if (! in_array($table, ['users'])) {
                        $query->where($table . '.is_deleted', '=', 0);
                    }
                });
            }

            if (in_array(parent::STATUS_DELETED, $filters)) {
                $query->orWhere($table . '.is_deleted', '=', 1);
            }
        });
    }

    /**
     * Sorts the list based on $sort
     * 
     * @param  string sort formatted as column|asc
     * @return Illuminate\Database\Query\Builder
     */
    public function sort(string $sort) : Builder
    {
        $sort_col = explode("|", $sort);
        return $this->builder->orderBy($sort_col[0], $sort_col[1]);
    }

    /**
     * Returns the base query
     * 
     * @param  int company_id
     * @return Illuminate\Database\Query\Builder
     * @deprecated
     */
    public function baseQuery(int $company_id, User $user) : Builder
    {

    }

    /**
     * Filters the query by the users company ID
     *
     * We need to ensure we are using the correct company ID
     * as we could be hitting this from either the client or company auth guard
     * 
     * @param $company_id The company Id
     * @return Illuminate\Database\Query\Builder
     */
    public function entityFilter()
    {

        if(auth('contact')->user())
            return $this->contactViewFilter();
        else
            return $this->builder->whereCompanyId(auth()->user()->company()->id);

    }

    /**
     * We need additional filters when showing invoices for the
     * client portal. Need to automatically exclude drafts and cancelled invoices
     * 
     * @return Illuminate\Database\Query\Builder
     */
    private function contactViewFilter() : Builder
    {

        return $this->builder
                    ->whereCompanyId(auth('contact')->user()->company->id)
                    ->whereNotIn('status_id', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED]);

    }



}