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

use App\Models\Company;
use App\Filters\QueryFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * DocumentFilters.
 */
class DocumentFilters extends QueryFilters
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

        return $this->builder->where('name', 'like', '%'.$filter.'%');

    }

    /**
     * Overriding method as client_id does
     * not exist on this model, just pass
     * back the builder
     *
     * @param  string $client_id The client hashed id.
     *
     * @return Builder
     */
    public function client_id(string $client_id = ''): Builder
    {
        
        return $this->builder->where(function ($query) use ($client_id) {
            $query->whereHasMorph('documentable', [
                \App\Models\Invoice::class, 
                \App\Models\Quote::class, 
                \App\Models\Credit::class, 
                \App\Models\Expense::class, 
                \App\Models\Payment::class, 
                \App\Models\Task::class,
                \App\Models\RecurringExpense::class,
                \App\Models\RecurringInvoice::class,
                \App\Models\Project::class,
            ], function ($q2) use ($client_id) {
                        $q2->where('client_id', $this->decodePrimaryKey($client_id));
                })->orWhereHasMorph('documentable', [\App\Models\Client::class], function ($q3) use ($client_id) {
                        $q3->where('id', $this->decodePrimaryKey($client_id));
            });
        });

    }

    public function type(string $types = '')
    {
        $types = explode(',', $types);

        foreach ($types as $type)
        {
            match($type) {
                'private' => $this->builder->where('is_public', 0),
                'public' => $this->builder->where('is_public', 1),
                'pdf' => $this->builder->where('type', 'pdf'),
                'image' => $this->builder->whereIn('type', ['png','jpeg','jpg','gif','svg']),
                'other' => $this->builder->whereNotIn('type', ['pdf','png','jpeg','jpg','gif','svg']),
                default => $this->builder,
            };
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


    public function company_documents($value = 'false')
    {
        if ($value == 'true') {
            return $this->builder->where('documentable_type', Company::class);
        }

        return $this->builder;
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
