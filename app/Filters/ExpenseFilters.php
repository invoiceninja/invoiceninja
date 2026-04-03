<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
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
            $query->where('number', 'like', '%' . $filter . '%')
                ->orWhere('amount', 'like', '%' . $filter . '%')
                ->orWhere('public_notes', 'like', '%' . $filter . '%')
                ->orWhere('custom_value1', 'like', '%' . $filter . '%')
                ->orWhere('custom_value2', 'like', '%' . $filter . '%')
                ->orWhere('custom_value3', 'like', '%' . $filter . '%')
                ->orWhere('custom_value4', 'like', '%' . $filter . '%')
                ->orWhereHas('category', function ($q) use ($filter) {
                    $q->where('name', 'like', '%' . $filter . '%');
                })
                ->orWhereHas('vendor', function ($q) use ($filter) {
                    $q->where('name', 'like', '%' . $filter . '%');
                })
                ->orWhereHas('client', function ($q) use ($filter) {
                    $q->where('name', 'like', '%' . $filter . '%');
                });
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
                    $query->where('amount', '>=', 0)
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

            if (in_array('uninvoiced', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNull('invoice_id');
                });
            }

            if (in_array('paid', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('payment_date')
                            ->orWhere('transaction_reference', '!=', '')
                            ->orWhereNotNull('transaction_reference');
                });
            }

            if (in_array('unpaid', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNull('payment_date')
                          ->where(function ($query) {
                              $query->where('transaction_reference', '')
                                   ->orWhereNull('transaction_reference');
                          });
                });
            }

            if (in_array('uncategorized', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNull('category_id');
                });
            }
        });

        // nlog($this->builder->toSql());

        return $this->builder;
    }

    /**
     * Filter expenses that only have invoices
     *
     * @param string $value
     * @return Builder
     */
    public function has_invoices(string $value = ''): Builder
    {
        $split = explode(",", $value);

        if (is_array($split) && in_array($split[0], ['client', 'project'])) {

            $search_key = $split[0] == 'client' ? 'client_id' : 'project_id';

            return $this->builder->whereHas('invoice', function ($query) use ($search_key, $split) {
                $query->where($search_key, $this->decodePrimaryKey($split[1]))
                      ->whereIn('status_id', [\App\Models\Invoice::STATUS_DRAFT, \App\Models\Invoice::STATUS_SENT, \App\Models\Invoice::STATUS_PARTIAL]);
            });
        }

        return $this->builder;
    }

    /**
     * Returns a list of expenses that can be matched to bank transactions
     */
    public function match_transactions($value = '')
    {
        if ($value == 'true') {
            return $this->builder->where('is_deleted', 0)
                                ->whereNull('transaction_id')
                                ->where(function ($query) {
                                    $query->whereHas('client', function ($sub_query) {
                                        $sub_query->where('is_deleted', 0)->where('deleted_at', null);
                                    })->orWhere('client_id', null);
                                })
                                ->where(function ($query) {
                                    $query->whereHas('vendor', function ($sub_query) {
                                        $sub_query->where('is_deleted', 0)->where('deleted_at', null);
                                    })->orWhere('vendor_id', null);
                                });
        }

        return $this->builder;
    }

    public function categories(string $categories = ''): Builder
    {
        $categories_exploded = explode(",", $categories);

        if (empty($categories) || count(array_filter($categories_exploded)) == 0) {
            return $this->builder;
        }

        $categories_keys = $this->transformKeys($categories_exploded);

        return $this->builder->whereIn('category_id', $categories_keys);
    }

    public function payment_type(string $payment_type = ''): Builder
    {
        $payment_types_exploded = explode(",", $payment_type);

        if (empty($payment_type) || count(array_filter($payment_types_exploded)) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('payment_type_id', $payment_types_exploded);
    }

    public function amount(string $amount = ''): Builder
    {
        if (strlen($amount) == 0) {
            return $this->builder;
        }

        return $this->builder->where('amount', $amount);
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
     * @param string $sort formatted as column|asc
     * @return Builder
     */
    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);


        // Handle relationship-based sorting
        if (is_array($sort_col) && count($sort_col) == 2 && $sort_col[0] == 'documents') {
            $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

            return $this->builder->withCount('documents')->orderBy('documents_count', $dir);
        }

        if (!is_array($sort_col) || count($sort_col) != 2 || (!in_array($sort_col[0], \Illuminate\Support\Facades\Schema::getColumnListing($this->builder->getModel()->getTable())) && !str_starts_with($sort_col[0], 'client.') && !str_starts_with($sort_col[0], 'contact.') && !str_starts_with($sort_col[0], 'documents'))) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        if (in_array($sort_col[0], ['client.name', 'client_id'])) {
            return $this->builder
                ->orderByRaw(
                    "
                    CASE
                        WHEN CHAR_LENGTH((SELECT name FROM clients WHERE clients.id = expenses.client_id LIMIT 1)) > 1
                            THEN (SELECT name FROM clients WHERE clients.id = expenses.client_id LIMIT 1)
                        WHEN CHAR_LENGTH(CONCAT(
                            COALESCE((SELECT first_name FROM client_contacts WHERE client_contacts.client_id = expenses.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1), ''),
                            COALESCE((SELECT last_name FROM client_contacts WHERE client_contacts.client_id = expenses.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1), '')
                        )) >= 1
                            THEN TRIM(CONCAT(
                                COALESCE((SELECT first_name FROM client_contacts WHERE client_contacts.client_id = expenses.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1), ''),
                                ' ',
                                COALESCE((SELECT last_name FROM client_contacts WHERE client_contacts.client_id = expenses.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1), '')
                            ))
                        WHEN CHAR_LENGTH((SELECT email FROM client_contacts WHERE client_contacts.client_id = expenses.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1)) > 0
                            THEN (SELECT email FROM client_contacts WHERE client_contacts.client_id = expenses.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1)
                        ELSE 'No Contact Set'
                    END " . $dir
                );
        }

        /** Relationship sorting - clients */
        if (str_starts_with($sort_col[0], 'client.')) {
            $client_parts = explode('.', $sort_col[0]);

            if (!isset($client_parts[1]) || !in_array($client_parts[1], \Illuminate\Support\Facades\Schema::getColumnListing('clients'))) {
                return $this->builder;
            }

            if ($sort_col[0] === 'client.country_id') {
                return $this->builder->orderBy(
                    \App\Models\Client::select('countries.name')
                        ->join('countries', 'countries.id', '=', 'clients.country_id')
                        ->whereColumn('clients.id', 'expenses.client_id')
                        ->limit(1),
                    $dir
                );
            }

            return $this->builder->orderBy(\App\Models\Client::select($client_parts[1])
                ->whereColumn('clients.id', 'expenses.client_id')
                ->limit(1), $dir);
        }

        /** Relationship sorting - contacts */
        if (str_starts_with($sort_col[0], 'contact.')) {
            $client_parts = explode('.', $sort_col[0]);

            if (!isset($client_parts[1]) || !in_array($client_parts[1], \Illuminate\Support\Facades\Schema::getColumnListing('client_contacts'))) {
                return $this->builder;
            }

            return $this->builder->orderBy(\App\Models\ClientContact::select($client_parts[1])
                ->whereColumn('client_contacts.client_id', 'expenses.client_id')
                ->limit(1), $dir);
        }

        if ($sort_col[0] == 'project' && in_array($sort_col[1], ['asc', 'desc'])) {
            return $this->builder
                    ->orderByRaw('ISNULL(project_id)')
                    ->orderBy(\App\Models\Project::select('name')
                    ->whereColumn('projects.id', 'expenses.project_id'), $sort_col[1]);
        }

        if ($sort_col[0] == 'vendor_id' && in_array($sort_col[1], ['asc', 'desc'])) {
            return $this->builder
                    ->orderByRaw('ISNULL(vendor_id)')
                    ->orderBy(\App\Models\Vendor::select('name')
                    ->whereColumn('vendors.id', 'expenses.vendor_id'), $sort_col[1]);

        }

        if ($sort_col[0] == 'category_id' && in_array($sort_col[1], ['asc', 'desc'])) {
            return $this->builder
                    ->orderByRaw('ISNULL(category_id)')
                    ->orderBy(\App\Models\ExpenseCategory::select('name')
                    ->whereColumn('expense_categories.id', 'expenses.category_id'), $sort_col[1]);
        }

        if ($sort_col[0] == 'payment_date' && in_array($sort_col[1], ['asc', 'desc'])) {
            return $this->builder
                    ->orderByRaw('ISNULL(payment_date), payment_date ' . $sort_col[1]);
        }

        if ($sort_col[0] == 'payment_type_id' && in_array($sort_col[1], ['asc', 'desc'])) {
            return $this->builder
                    ->orderByRaw('ISNULL(payment_type_id)')
                    ->orderBy(\App\Models\PaymentType::select('name')
                    ->whereColumn('payment_types.id', 'expenses.payment_type_id'), $sort_col[1]);
        }

        if ($sort_col[0] == 'number') {
            return $this->builder->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . $dir);
        }

        if (is_array($sort_col) && in_array($sort_col[1], ['asc', 'desc']) && in_array($sort_col[0], ['amount', 'public_notes', 'date', 'id_number', 'custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'])) {
            return $this->builder->orderBy($sort_col[0], $sort_col[1]);
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
