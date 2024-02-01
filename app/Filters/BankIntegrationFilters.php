<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * BankIntegrationFilters.
 */
class BankIntegrationFilters extends QueryFilters
{
    /**
     * Filter by name.
     *
     * @param string $name
     * @return Builder
     */
    public function name(string $name = ''): Builder
    {
        if (strlen($name) == 0) {
            return $this->builder;
        }

        return $this->builder->where('bank_account_name', 'like', '%'.$name.'%');
    }

    /**
     * Filter based on search text.
     *
     * @param string $filter
     * @return Builder
     * @deprecated
     */
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return  $this->builder->where(function ($query) use ($filter) {
            $query->where('bank_account_name', 'like', '%'.$filter.'%');
        });
    }

    /**
     * Filters the list based on the status
     * archived, active, deleted.
     *
     * @param string $filter
     * @return Builder
     */
    public function status(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        $filters = explode(',', $filter);

        return $this->builder->where(function ($query) use ($filters) {
            if (in_array(parent::STATUS_ACTIVE, $filters)) {
                $query->orWhereNull('deleted_at');
            }

            if (in_array(parent::STATUS_ARCHIVED, $filters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('deleted_at');
                });
            }

            if (in_array(parent::STATUS_DELETED, $filters)) {
                $query->orWhere('is_deleted', 1);
            }
        });
    }

    /**
     * Sorts the list based on $sort.
     *
     * @param string $sort formatted as column|asc
     * @return Builder
     */
    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);

        if (!is_array($sort_col) || count($sort_col) != 2) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        return $this->builder->orderBy($sort_col[0], $dir);
    }

    /**
     * Filters the query by the users company ID.
     *
     * @return Builder
     */
    public function entityFilter(): Builder
    {
        return $this->builder->company();
    }
}
