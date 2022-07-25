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

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * ClientFilters.
 */
class ClientFilters extends QueryFilters
{
    /**
     * Filter by name.
     *
     * @param string $name
     * @return Builder
     */
    public function name(string $name): Builder
    {
        return $this->builder->where('name', 'like', '%'.$name.'%');
    }

    /**
     * Filter by balance.
     *
     * @param string $balance
     * @return Builder
     */
    public function balance(string $balance): Builder
    {
        $parts = $this->split($balance);

        return $this->builder->where('balance', $parts->operator, $parts->value);
    }

    /**
     * Filter between balances.
     *
     * @param string balance
     * @return Builder
     */
    public function between_balance(string $balance): Builder
    {
        $parts = explode(':', $balance);

        if (! is_array($parts)) {
            return $this->builder;
        }

        return $this->builder->whereBetween('balance', [$parts[0], $parts[1]]);
    }

    public function email(string $email = ''):Builder
    {
        return

        $this->builder->whereHas('contacts', function ($query) use ($email) {
            $query->where('email', $email);
        });
    }

    public function client_id(string $client_id = '') :Builder
    {
        if (strlen($client_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('id', $this->decodePrimaryKey($client_id));
    }

    public function id_number(string $id_number = ''):Builder
    {
        return $this->builder->where('id_number', $id_number);
    }

    public function number(string $number = ''):Builder
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
            $query->where('clients.name', 'like', '%'.$filter.'%')
                          ->orWhere('clients.id_number', 'like', '%'.$filter.'%')
                          ->orWhereHas('contacts', function ($query) use ($filter) {
                              $query->where('first_name', 'like', '%'.$filter.'%');
                              $query->orWhere('last_name', 'like', '%'.$filter.'%');
                              $query->orWhere('email', 'like', '%'.$filter.'%');
                          })
                          ->orWhere('clients.custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('clients.custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('clients.custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('clients.custom_value4', 'like', '%'.$filter.'%');
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

        $table = 'clients';
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
        $query = DB::table('clients')
            ->join('companies', 'companies.id', '=', 'clients.company_id')
            ->join('client_contacts', 'client_contacts.client_id', '=', 'clients.id')
            ->where('clients.company_id', '=', $company_id)
            ->where('client_contacts.is_primary', '=', true)
            ->where('client_contacts.deleted_at', '=', null)
            //->whereRaw('(clients.name != "" or contacts.first_name != "" or contacts.last_name != "" or contacts.email != "")') // filter out buy now invoices
            ->select(
               // DB::raw('COALESCE(clients.currency_id, companies.currency_id) currency_id'),
                DB::raw('COALESCE(clients.country_id, companies.country_id) country_id'),
                DB::raw("CONCAT(COALESCE(client_contacts.first_name, ''), ' ', COALESCE(client_contacts.last_name, '')) contact"),
                'clients.id',
                'clients.name',
                'clients.private_notes',
                'client_contacts.first_name',
                'client_contacts.last_name',
                'clients.custom_value1',
                'clients.custom_value2',
                'clients.custom_value3',
                'clients.custom_value4',
                'clients.balance',
                'clients.last_login',
                'clients.created_at',
                'clients.created_at as client_created_at',
                'client_contacts.phone',
                'client_contacts.email',
                'clients.deleted_at',
                'clients.is_deleted',
                'clients.user_id',
                'clients.id_number',
                'clients.settings'
            );

        /*
         * If the user does not have permissions to view all invoices
         * limit the user to only the invoices they have created
         */
        if (Gate::denies('view-list', Client::class)) {
            $query->where('clients.user_id', '=', $user->id);
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
