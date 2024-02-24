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
            $query->where('first_name', 'like', '%'.$filter.'%')
                          ->orWhere('last_name', 'like', '%'.$filter.'%')
                          ->orWhere('email', 'like', '%'.$filter.'%')
                          ->orWhere('signature', 'like', '%'.$filter.'%');
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

        if (!is_array($sort_col) || count($sort_col) != 2 || !in_array($sort_col[0], \Illuminate\Support\Facades\Schema::getColumnListing('users'))) {
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
    public function entityFilter()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->builder->whereHas('company_users', function ($q) use ($user) {
            $q->where('company_id', '=', $user->company()->id);
        });
    }

    /**
     * Hides owner users from the list.
     *
     * @return Builder
     */
    public function hideOwnerUsers(): Builder
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->builder->whereHas('company_users', function ($q) use ($user) {
            $q->where('company_id', '=', $user->company()->id)->where('is_owner', false);
        });

    }

    /**
     * Filters users that have been removed from the
     * company, but not deleted from the system.
     *
     * @return Builder
     */
    public function hideRemovedUsers(): Builder
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->builder->whereHas('company_users', function ($q) use ($user) {
            $q->where('company_id', '=', $user->company()->id)->whereNull('deleted_at');
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

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->builder
            ->orWhere($this->with_property, $value)
            ->orderByRaw("{$this->with_property} = ? DESC", [$value])
            ->where('account_id', $user->account_id);
    }

    /**
     * Returns users with permissions to send emails via OAuth
     *
     * @param  string $value
     * @return Builder
     */
    public function sending_users(string $value = ''): Builder
    {
        if (strlen($value) == 0 || $value != 'true') {
            return $this->builder;
        }

        return $this->builder->whereNotNull('oauth_user_refresh_token');
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
            $query->whereNotIn('id', $user_array);
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

            /** @var \App\Models\User $user */
            $user = auth()->user();

            if (in_array(self::STATUS_ACTIVE, $filters)) {
                $query->orWhereNull('deleted_at');
            }

            if (in_array(self::STATUS_ARCHIVED, $filters)) {
                $query->orWhereNotNull('deleted_at')->where('is_deleted', 0);
            }

            if (in_array(self::STATUS_DELETED, $filters)) {
                $query->orWhere('is_deleted', 1);
            }
        });
    }


}
