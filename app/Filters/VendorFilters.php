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

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * VendorFilters.
 */
class VendorFilters extends QueryFilters
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
            $query->where('vendors.name', 'like', '%'.$filter.'%')
                          ->orWhere('vendors.id_number', 'like', '%'.$filter.'%')
                          ->orWhereHas('contacts', function ($query) use ($filter) {
                              $query->where('vendor_contacts.first_name', 'like', '%'.$filter.'%');
                              $query->orWhere('vendor_contacts.last_name', 'like', '%'.$filter.'%');
                              $query->orWhere('vendor_contacts.email', 'like', '%'.$filter.'%');
                          })
                          ->orWhere('vendors.custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('vendors.custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('vendors.custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('vendors.custom_value4', 'like', '%'.$filter.'%');
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

        $table = 'vendors';
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
        $query = DB::table('vendors')
            ->join('companies', 'companies.id', '=', 'vendors.company_id')
            ->join('vendor_contacts', 'vendor_contacts.vendor_id', '=', 'vendors.id')
            ->where('vendors.company_id', '=', $company_id)
            ->where('vendor_contacts.is_primary', '=', true)
            ->where('vendor_contacts.deleted_at', '=', null)
            //->whereRaw('(vendors.name != "" or contacts.first_name != "" or contacts.last_name != "" or contacts.email != "")') // filter out buy now invoices
            ->select(
               // DB::raw('COALESCE(vendors.currency_id, companies.currency_id) currency_id'),
                DB::raw('COALESCE(vendors.country_id, companies.country_id) country_id'),
                DB::raw("CONCAT(COALESCE(vendor_contacts.first_name, ''), ' ', COALESCE(vendor_contacts.last_name, '')) contact"),
                'vendors.id',
                'vendors.name',
                'vendors.private_notes',
                'vendor_contacts.first_name',
                'vendor_contacts.last_name',
                'vendors.custom_value1',
                'vendors.custom_value2',
                'vendors.custom_value3',
                'vendors.custom_value4',
                'vendors.balance',
                'vendors.last_login',
                'vendors.created_at',
                'vendors.created_at as vendor_created_at',
                'vendor_contacts.phone',
                'vendor_contacts.email',
                'vendors.deleted_at',
                'vendors.is_deleted',
                'vendors.user_id',
                'vendors.id_number',
                'vendors.settings'
            );

        /*
         * If the user does not have permissions to view all invoices
         * limit the user to only the invoices they have created
         */
        if (Gate::denies('view-list', Vendor::class)) {
            $query->where('vendors.user_id', '=', $user->id);
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

        //return $this->builder->whereCompanyId(auth()->user()->company()->id);
        return $this->builder->company();
    }
}
