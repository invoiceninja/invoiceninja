<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Filters;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * TokenFilters.
 */
class WebhookFilters extends QueryFilters
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
            $query->where('webhooks.target_url', 'like', '%'.$filter.'%');
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

        $table = 'webhooks';
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

        return $this->builder->orderBy($sort_col[0], $sort_col[1]);
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
        $query = DB::table('webhooks')
            ->join('companies', 'companies.id', '=', 'webhooks.company_id')
            ->where('webhooks.company_id', '=', $company_id)
            //->whereRaw('(designs.name != "" or contacts.first_name != "" or contacts.last_name != "" or contacts.email != "")') // filter out buy now invoices
            ->select(
                'webhooks.id',
                'webhooks.target_url',
                'webhooks.event_id',
                'webhooks.created_at',
                'webhooks.created_at as token_created_at',
                'webhooks.deleted_at',
                'webhooks.format',
                'webhooks.user_id',
            );

        /*
         * If the user does not have permissions to view all invoices
         * limit the user to only the invoices they have created
         */
        if (Gate::denies('view-list', Webhook::class)) {
            $query->where('webhooks.user_id', '=', $user->id);
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
        return $this->builder->company();
    }
}
