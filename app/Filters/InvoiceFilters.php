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

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * InvoiceFilters.
 */
class InvoiceFilters extends QueryFilters
{
    use MakesHash;

    /**
     * Filter based on client status.
     *
     * Statuses we need to handle
     * - all
     * - paid
     * - unpaid
     * - overdue
     * - reversed
     *
     * @param string client_status The invoice status as seen by the client
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
            $invoice_filters = [];

            if (in_array('paid', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_PAID;
            }

            if (in_array('unpaid', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_SENT;
                $invoice_filters[] = Invoice::STATUS_PARTIAL;
            }

            if (count($invoice_filters) >0) {
                $query->whereIn('status_id', $invoice_filters);
            }
            
            if (in_array('overdue', $status_parameters)) {
                $query->orWhereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                ->where('due_date', '<', Carbon::now())
                                ->orWhere('partial_due_date', '<', Carbon::now());
            }
        });

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

        return $this->builder->where(function ($query) use ($filter) {
            $query->where('number', 'like', '%'.$filter.'%')
                          ->orWhere('po_number', 'like', '%'.$filter.'%')
                          ->orWhere('date', 'like', '%'.$filter.'%')
                          ->orWhere('amount', 'like', '%'.$filter.'%')
                          ->orWhere('balance', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value4', 'like', '%'.$filter.'%')
                          ->orWhereHas('client', function ($q) use ($filter) {
                              $q->where('name', 'like', '%'.$filter.'%');
                          });
        });
    }

    /**
     * @return Builder
     * @throws RuntimeException
     */
    public function without_deleted_clients(): Builder
    {
        return $this->builder->whereHas('client', function ($query) {
            $query->where('is_deleted', 0);
        });
    }

    /**
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function upcoming(): Builder
    {
        return $this->builder
                    ->where(function ($query) {
                        $query->whereNull('due_date')
                              ->orWhere('due_date', '>', now());
                    })
                    ->orderBy('due_date', 'ASC');
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function overdue(): Builder
    {
        return $this->builder->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_deleted', 0)
                    ->where(function ($query) {
                        $query->where('due_date', '<', now())
                            ->orWhere('partial_due_date', '<', now());
                    })
                    ->orderBy('due_date', 'ASC');
    }

    /**
     * @param string $client_id
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function payable(string $client_id = ''): Builder
    {
        if (strlen($client_id) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('status_id', [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                             ->where('balance', '>', 0)
                             ->where('is_deleted', 0)
                             ->where('client_id', $this->decodePrimaryKey($client_id));
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
     * We need to ensure we are using the correct company ID
     * as we could be hitting this from either the client or company auth guard
     *
     * @return Illuminate\Database\Query\Builder
     */
    public function entityFilter()
    {
        if (auth()->guard('contact')->user()) {
            return $this->contactViewFilter();
        } else {
            return $this->builder->company()->with(['invitations.company'], ['documents.company']);
        }
    }

    /**
     * @param string $filter
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function private_notes($filter = '') :Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->where('private_notes', 'LIKE', '%'.$filter.'%');
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
                    ->whereNotIn('status_id', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED]);
    }
}
