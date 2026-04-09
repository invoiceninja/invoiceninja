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
 * ProjectFilters.
 */
class ProjectFilters extends QueryFilters
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
            $query->where('name', 'like', '%' . $filter . '%')
                  ->orWhereHas('client', function ($q) use ($filter) {
                      $q->where('name', 'like', '%' . $filter . '%');
                  })
                ->orWhere('public_notes', 'like', '%' . $filter . '%')
                ->orWhere('private_notes', 'like', '%' . $filter . '%')
                ->orWhere('custom_value1', 'like', '%' . $filter . '%')
                ->orWhere('custom_value2', 'like', '%' . $filter . '%')
                ->orWhere('custom_value3', 'like', '%' . $filter . '%')
                ->orWhere('custom_value4', 'like', '%' . $filter . '%');
        });
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

        if (!is_array($sort_col) || count($sort_col) != 2 || (!in_array($sort_col[0], \Illuminate\Support\Facades\Schema::getColumnListing('projects')) && !str_starts_with($sort_col[0], 'client.') && !str_starts_with($sort_col[0], 'contact.') && !str_starts_with($sort_col[0], 'documents'))) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        if ($sort_col[0] == 'documents') {
            return $this->builder->withCount('documents')->orderBy('documents_count', $dir);
        }

        if (in_array($sort_col[0], ['client.name', 'client_id'])) {
            return $this->builder
                ->orderByRaw(
                    "
                    CASE
                        WHEN CHAR_LENGTH((SELECT name FROM clients WHERE clients.id = projects.client_id LIMIT 1)) > 1
                            THEN (SELECT name FROM clients WHERE clients.id = projects.client_id LIMIT 1)
                        WHEN CHAR_LENGTH(CONCAT(
                            COALESCE((SELECT first_name FROM client_contacts WHERE client_contacts.client_id = projects.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1), ''),
                            COALESCE((SELECT last_name FROM client_contacts WHERE client_contacts.client_id = projects.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1), '')
                        )) >= 1
                            THEN TRIM(CONCAT(
                                COALESCE((SELECT first_name FROM client_contacts WHERE client_contacts.client_id = projects.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1), ''),
                                ' ',
                                COALESCE((SELECT last_name FROM client_contacts WHERE client_contacts.client_id = projects.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1), '')
                            ))
                        WHEN CHAR_LENGTH((SELECT email FROM client_contacts WHERE client_contacts.client_id = projects.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1)) > 0
                            THEN (SELECT email FROM client_contacts WHERE client_contacts.client_id = projects.client_id AND client_contacts.email IS NOT NULL ORDER BY client_contacts.is_primary DESC, client_contacts.id ASC LIMIT 1)
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
                        ->whereColumn('clients.id', 'projects.client_id')
                        ->limit(1),
                    $dir
                );
            }

            return $this->builder->orderBy(\App\Models\Client::select($client_parts[1])
                ->whereColumn('clients.id', 'projects.client_id')
                ->limit(1), $dir);
        }

        /** Relationship sorting - contacts */
        if (str_starts_with($sort_col[0], 'contact.')) {
            $client_parts = explode('.', $sort_col[0]);

            if (!isset($client_parts[1]) || !in_array($client_parts[1], \Illuminate\Support\Facades\Schema::getColumnListing('client_contacts'))) {
                return $this->builder;
            }

            return $this->builder->orderBy(\App\Models\ClientContact::select($client_parts[1])
                ->whereColumn('client_contacts.client_id', 'projects.client_id')
                ->limit(1), $dir);
        }

        if ($sort_col[0] == 'number') {
            return $this->builder->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . $dir);
        }

        return $this->builder->orderBy($sort_col[0], $dir);

    }

    /**
     * date_range
     *
     * only filters on date
     * @param  string $date_range in format column,start_date,end_date
     * @return Builder
     */
    public function date_range(string $date_range = ''): Builder
    {
        $parts = explode(",", $date_range);

        if (count($parts) != 3 || !in_array($parts[0], \Illuminate\Support\Facades\Schema::getColumnListing($this->builder->getModel()->getTable()))) {
            return $this->builder;
        }

        try {

            $start_date = \Carbon\Carbon::parse($parts[1]);
            $end_date = \Carbon\Carbon::parse($parts[2]);


            return $this->builder->whereBetween($parts[0], [$start_date, $end_date]);
        } catch (\Exception $e) {
            return $this->builder;
        }

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
