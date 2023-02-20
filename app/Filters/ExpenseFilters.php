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
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return  $this->builder->where(function ($query) use ($filter) {
            $query->where('public_notes', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value4', 'like', '%'.$filter.'%');
        });
    }

    /**
     * Filter based on client status.
     *
     * Statuses we need to handle
     * - all
     * - logged
     * - pending
     * - invoiced
     * - paid
     * - unpaid
     *
     * @return Builder
     */
    public function client_status(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        $status_parameters = explode(',', $value);

        if (in_array('all', $status_parameters)) {
            return $this->builder;
        }

        $this->builder->where(function ($query) use ($status_parameters) {
            if (in_array('logged', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->where('amount', '>', 0)
                          ->whereNull('invoice_id')
                          ->whereNull('payment_date')
                          ->where('should_be_invoiced', false);
                });
            }

            if (in_array('pending', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->where('should_be_invoiced', true)
                          ->whereNull('invoice_id');
                });
            }

            if (in_array('invoiced', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('invoice_id');
                });
            }

            if (in_array('paid', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('payment_date');
                });
            }

            if (in_array('unpaid', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNull('payment_date');
                });
            }
        });

        // nlog($this->builder->toSql());

        return $this->builder;
    }

    /**
     * Returns a list of expenses that can be matched to bank transactions
     */
    public function match_transactions($value = '')
    {
        if ($value == 'true') {
            return $this->builder->where('is_deleted', 0)->whereNull('transaction_id');
        }

        return $this->builder;
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
     * @return Builder
     */
    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);

        if (!is_array($sort_col) || count($sort_col) != 2) {
            return $this->builder;
        }

        if (is_array($sort_col) && in_array($sort_col[1], ['asc', 'desc']) && in_array($sort_col[0], ['public_notes', 'date', 'id_number', 'custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'])) {
            return $this->builder->orderBy($sort_col[0], $sort_col[1]);
        }

        return $this->builder;
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
