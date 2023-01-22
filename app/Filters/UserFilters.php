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

use Illuminate\Database\Eloquent\Builder;

/**
 * UserFilters.
 */
class UserFilters extends QueryFilters
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
            $query->where('users.first_name', 'like', '%'.$filter.'%')
                          ->orWhere('users.last_name', 'like', '%'.$filter.'%')
                          ->orWhere('users.email', 'like', '%'.$filter.'%')
                          ->orWhere('users.signature', 'like', '%'.$filter.'%');
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

        return $this->builder->orderBy($sort_col[0], $sort_col[1]);
    }

    /**
     * Filters the query by the users company ID.
     *
     * @return Builder
     */
    public function entityFilter()
    {
        return $this->builder->whereHas('company_users', function ($q) {
            $q->where('company_id', '=', auth()->user()->company()->id);
        });
    }

    /**
     * Overrides the base with() function as no company ID 
     * exists on the user table
     * 
     * @param  string $value Hashed ID of the user to return back in the dataset
     * 
     * @return Builder
     */
    public function with(string $value = ''): Builder
    {

        if(strlen($value) == 0)
            return $this->builder;

        return $this->builder
            ->orWhere($this->with_property, $value)
            ->orderByRaw("{$this->with_property} = ? DESC", [$value])
            ->where('account_id', auth()->user()->account_id);
    }

}
