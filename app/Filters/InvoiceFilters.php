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

use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
    public function client_status(string $value = '') :Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        $status_parameters = explode(',', $value);

        if (in_array('all', $status_parameters)) {
            return $this->builder;
        }

        if (in_array('paid', $status_parameters)) {
            $this->builder->where('status_id', Invoice::STATUS_PAID);
        }

        if (in_array('unpaid', $status_parameters)) {
            $this->builder->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL]);
        }
        //->where('due_date', '>', Carbon::now())
        //->orWhere('partial_due_date', '>', Carbon::now());

        if (in_array('overdue', $status_parameters)) {
            $this->builder->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                            ->where('due_date', '<', Carbon::now())
                            ->orWhere('partial_due_date', '<', Carbon::now());
        }

        return $this->builder;
    }

    public function number(string $number = '') :Builder
    {
        return $this->builder->where('number', $number);
    }

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
            $query->where('invoices.number', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.po_number', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.date', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.amount', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.balance', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('invoices.custom_value4', 'like', '%'.$filter.'%');
        });
    }


    /**
     * Filters the list based on the status
     * archived, active, deleted - legacy from V1.
     *
     * @param string filter
     * @return Builder
     */
    public function status(string $filter = '') : Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        $table = 'invoices';
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

    public function without_deleted_clients()
    {

        return $this->builder->whereHas('client', function ($query) {
                        $query->where('is_deleted',0);
                       });
    }

    public function upcoming()
    {
        return $this->builder
                    ->where(function ($query) {
                        $query->whereNull('due_date')
                              ->orWhere('due_date', '>', now());
                    })
                    ->orderBy('due_date', 'ASC');
    }

    public function overdue()
    {
        $this->builder->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                ->where('is_deleted', 0)
                ->where(function ($query) {
                        $query->where('due_date', '<', now())
                        ->orWhere('partial_due_date', '<', now());
                })
                ->orderBy('due_date', 'ASC');
    }

    public function payable(string $client_id)
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
    public function sort(string $sort) : Builder
    {
        $sort_col = explode('|', $sort);

        //catch invalid explode array count
        if(count($sort_col) == 1)
            return $this->builder;

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

//            return $this->builder->whereCompanyId(auth()->user()->company()->id);
    }

    /**
     * We need additional filters when showing invoices for the
     * client portal. Need to automatically exclude drafts and cancelled invoices.
     *
     * @return Builder
     */
    private function contactViewFilter() : Builder
    {
        return $this->builder
                    ->whereCompanyId(auth()->guard('contact')->user()->company->id)
                    ->whereNotIn('status_id', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED]);
    }
}
