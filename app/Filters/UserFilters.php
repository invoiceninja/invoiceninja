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
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return  $this->builder->where(function ($query) use ($filter) {
            $query->where('first_name', 'like', '%'.$filter.'%')
                          ->orWhere('last_name', 'like', '%'.$filter.'%')
                          ->orWhere('email', 'like', '%'.$filter.'%')
                          ->orWhere('signature', 'like', '%'.$filter.'%');
        });
    }


    /**
     * Sorts the list based on $sort.
     *
     * @param string sort formatted as column|asc
     * @return Builder
     */
    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);

        if (!is_array($sort_col) || count($sort_col) != 2) {
            return $this->builder;
        }

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
        if (strlen($value) == 0) {
            return $this->builder;
        }

        return $this->builder
            ->orWhere($this->with_property, $value)
            ->orderByRaw("{$this->with_property} = ? DESC", [$value])
            ->where('account_id', auth()->user()->account_id);
    }
    
    /**
     * Exclude a list of user_ids, can pass multiple
     * user IDs by separating them with a comma.
     *
     * @param  string $user_id
     * @return Builder
     */
    public function without(string $user_id = ''): Builder
    {
        if (strlen($user_id) == 0) {
            return $this->builder;
        }

        $user_array = $this->transformKeys(explode(',', $user_id));

        return  $this->builder->where(function ($query) use ($user_array) {
            $query->whereNotIn('id', $user_array)
                  ->where('account_id', auth()->user()->account_id);
        });
    }
}
