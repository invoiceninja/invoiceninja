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

use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * RecurringInvoiceFilters.
 */
class RecurringInvoiceFilters extends QueryFilters
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
            $query->where('recurring_invoices.custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('recurring_invoices.custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('recurring_invoices.custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('recurring_invoices.custom_value4', 'like', '%'.$filter.'%');
        });
    }

    /**
     * Filter based on client status.
     *
     * Statuses we need to handle
     * - all
     * - active
     * - paused
     * - completed
     *
     * @param string client_status The invoice status as seen by the client
     * @return Builder
     */
    public function client_status(string $value = '') :Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        $status_parameters = explode(',', $value);

        if (in_array('all', $status_parameters)) {
            return $this->builder;
        }

        $recurring_filters = [];

        if (in_array('active', $status_parameters)) 
            $recurring_filters[] = RecurringInvoice::STATUS_ACTIVE;


        if (in_array('paused', $status_parameters)) 
            $recurring_filters[] = RecurringInvoice::STATUS_PAUSED;

        if (in_array('completed', $status_parameters)) 
            $recurring_filters[] = RecurringInvoice::STATUS_COMPLETED;

        return $this->builder->whereIn('status_id', $recurring_filters);

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
     * @return Illuminate\Database\Query\Builder
     */
    public function entityFilter()
    {
        return $this->builder->company();
    }
}
