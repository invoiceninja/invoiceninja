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

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * QuoteFilters.
 */
class QuoteFilters extends QueryFilters
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
            $query->where('quotes.number', 'like', '%'.$filter.'%')
                  ->orwhere('quotes.custom_value1', 'like', '%'.$filter.'%')
                  ->orWhere('quotes.custom_value2', 'like', '%'.$filter.'%')
                  ->orWhere('quotes.custom_value3', 'like', '%'.$filter.'%')
                  ->orWhere('quotes.custom_value4', 'like', '%'.$filter.'%');
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

        $quote_filters = [];

        $this->builder->where(function ($query) use ($status_parameters){

            if (in_array('draft', $status_parameters)) {
                $query->orWhere(function ($q){
                    $q->where('status_id', Quote::STATUS_DRAFT);
                });

            }

            if (in_array('sent', $status_parameters)) {
                $query->orWhere(function ($q){
                              $q->where('status_id', Quote::STATUS_SENT)
                              ->whereNull('due_date')
                              ->orWhere('due_date', '>=', now()->toDateString());
                          });
            }

            if (in_array('approved', $status_parameters)) {
                $query->orWhere(function ($q){
                    $q->where('status_id', Quote::STATUS_APPROVED)->company();
                });
            }


            if (in_array('expired', $status_parameters)) {
                $query->orWhere(function ($q){
                              $q->where('status_id', Quote::STATUS_SENT)
                              ->whereNotNull('due_date')
                              ->where('due_date', '<=', now()->toDateString());
                          });
            }

            if (in_array('upcoming', $status_parameters)) {
                $query->orWhere(function ($q){
                            $q->where('status_id', Quote::STATUS_SENT)
                              ->where('due_date', '>=', now()->toDateString())
                              ->orderBy('due_date', 'DESC');
                          });
            }

        });

        return $this->builder;
    }

    public function number($number = '')
    {
        return $this->builder->where('number', 'like', '%'.$number.'%');
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

        if($sort_col[0] == 'valid_until')
            $sort_col[0] = 'due_date';

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
