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
 * PaymentFilters.
 */
class PaymentFilters extends QueryFilters
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
            $query->where('amount', 'like', '%'.$filter.'%')
                          ->orWhere('date', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value4', 'like', '%'.$filter.'%');
        });
    }

    /**
     * Returns a list of payments that can be matched to bank transactions
     */
    public function match_transactions($value = 'true'): Builder
    {

        if($value == 'true'){

            return $this->builder
                        ->where('is_deleted',0)
                        ->where(function ($query){
                            $query->whereNull('transaction_id')
                            ->orWhere("transaction_id","")
                            ->company();
                        });
                        
        }

        return $this->builder;
    }

    /**
     * Sorts the list based on $sort.
     *
     * @param string sort formatted as column|asc
     * @return Builder
     */
    public function sort(string $sort): Builder
    {
        $sort_col = explode('|', $sort);

        if(is_array($sort_col))
            return $this->builder->orderBy($sort_col[0], $sort_col[1]);

        return true;
    }

    public function number(string $number = ''): Builder
    {
        return $this->builder->where('number', $number);
    }

    /**
     * Filters the query by the users company ID.
     *
     * @return Illuminate\Database\Query\Builder
     */
    public function entityFilter(): Builder
    {
        if (auth()->guard('contact')->user()) {
            return $this->contactViewFilter();
        } else {
            return $this->builder->company();
        }
    }

    /**
     * We need additional filters when showing invoices for the
     * client portal. Need to automatically exclude drafts and cancelled invoices.
     *
     * @return Builder
     */
    private function contactViewFilter(): Builder
    {
        return $this->builder
                    ->whereCompanyId(auth()->guard('contact')->user()->company->id)
                    ->whereIsDeleted(false);
    }
}
