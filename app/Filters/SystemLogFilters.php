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
 * SystemLogFilters.
 */
class SystemLogFilters extends QueryFilters
{
    public function type_id(string $type_id = ''): Builder
    {
        if (strlen($type_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('type_id', $type_id);
    }

    public function category_id(string $category_id = ''): Builder
    {
        if (strlen($category_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('category_id', $category_id);
    }

    public function event_id(string $event_id = ''): Builder
    {
        if (strlen($event_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('event_id', $event_id);
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

        return $this->builder;
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
