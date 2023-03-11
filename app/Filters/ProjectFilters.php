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
 * ProjectFilters.
 */
class ProjectFilters extends QueryFilters
{
    /**
     * Filter based on search text.
     *
     * @param string query filter
     * @return Illuminate\Eloquent\Query\Builder
     * @deprecated
     */
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return  $this->builder->where(function ($query) use ($filter) {
            $query->where('name', 'like', '%'.$filter.'%')
                  ->orWhere('public_notes', 'like', '%'.$filter.'%')
                  ->orWhere('private_notes', 'like', '%'.$filter.'%');
        });
    }
    
    public function number(string $number = ''): Builder
    {
        if (strlen($number) == 0) {
            return $this->builder;
        }

        return $this->builder->where('number', $number);
    }

    /**
     * Sorts the list based on $sort.
     *
     * @param string sort formatted as column|asc
     * @return Illuminate\Eloquent\Query\Builder
     */
    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);

        if (!is_array($sort_col) || count($sort_col) != 2) {
            return $this->builder;
        }

        if (is_array($sort_col)) {
            return $this->builder->orderBy($sort_col[0], $sort_col[1]);
        }
    }

    /**
     * Filters the query by the users company ID.
     *
     * @return Illuminate\Eloquent\Query\Builder
     */
    public function entityFilter(): Builder
    {
        return $this->builder->company();
    }
}
