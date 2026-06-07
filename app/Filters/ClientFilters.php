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
    public function name(string $name = ''): Builder
    {
        if (strlen($name) == 0) {
            return $this->builder;
        }

        return $this->builder->where('name', 'like', '%' . $name . '%');
    }

    /**
     * Filter by balance.
     *
     * @param string $balance
     * @return Builder
     */
    public function balance(string $balance = ''): Builder
    {
        if (strlen($balance) == 0 || count(explode(":", $balance)) < 2) {
            return $this->builder;
        }

        $parts = $this->split($balance);

        return $this->builder->where('balance', $parts->operator, $parts->value);
    }

    /**
     * Filter between balances.
     *
     * @param string $balance
     * @return Builder
     */
    public function between_balance(string $balance = ''): Builder
    {
        $parts = explode(':', $balance);

        if (!is_array($parts) || count($parts) != 2) {
            return $this->builder;
        }

        return $this->builder->whereBetween('balance', [$parts[0], $parts[1]]);
    }

    public function email(string $email = ''): Builder
    {
        if (strlen($email) == 0) {
            return $this->builder;
        }

        return $this->builder->whereHas('contacts', function ($query) use ($email) {
            $query->where('email', $email);
        });
    }

    public function client_id(string $client_id = ''): Builder
    {
        if (strlen($client_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('id', $this->decodePrimaryKey($client_id));
    }

    public function id_number(string $id_number = ''): Builder
    {
        if (strlen($id_number) == 0) {
            return $this->builder;
        }

        return $this->builder->where('id_number', $id_number);
    }

    public function number(string $number = ''): Builder
    {
        if (strlen($number) == 0) {
            return $this->builder;
        }

        return $this->builder->where('number', $number);
    }

    public function group(string $group_id = ''): Builder
    {
        if (strlen($group_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('group_settings_id', $this->decodePrimaryKey($group_id));

    }

    public function group_settings_id(string $group_settings_id = ''): Builder
    {
        $groups = explode(',', $group_settings_id);

        if (strlen($group_settings_id) == 0 || count(array_filter($groups)) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('group_settings_id', $this->transformKeys($groups));
    }

    public function country_id(string $country_id = ''): Builder
    {
        $countries = explode(',', $country_id);

        if (strlen($country_id) == 0 || count(array_filter($countries)) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('country_id', $countries);
    }

    public function industry_id(string $industry_id = ''): Builder
    {
        $industries = explode(',', $industry_id);

        if (strlen($industry_id) == 0 || count(array_filter($industries)) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('industry_id', $industries);
    }

    public function size_id(string $size_id = ''): Builder
    {
        $sizes = explode(',', $size_id);

        if (strlen($size_id) == 0 || count(array_filter($sizes)) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('size_id', $sizes);
    }

    public function classification(string $classification = ''): Builder
    {
        $classifications = explode(',', $classification);

        if (strlen($classification) == 0 || count(array_filter($classifications)) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('classification', $classifications);
    }

    public function vat_number(string $vat_number = ''): Builder
    {
        if (strlen($vat_number) == 0) {
            return $this->builder;
        }

        return $this->builder->where('vat_number', 'like', '%' . $vat_number . '%');
    }

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

        $searchTerms = array_filter(explode(' ', $filter));

        return $this->builder->where(function ($query) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $query->where(function ($subQuery) use ($term) {
                    $subQuery->where('name', 'like', '%' . $term . '%')
                        ->orWhere('id_number', 'like', '%' . $term . '%')
                        ->orWhere('number', 'like', '%' . $term . '%')
                        ->orWhereHas('contacts', function ($contactQuery) use ($term) {
                            $contactQuery->where('first_name', 'like', '%' . $term . '%')
                                ->orWhere('last_name', 'like', '%' . $term . '%')
                                ->orWhere('email', 'like', '%' . $term . '%')
                                ->orWhere('phone', 'like', '%' . $term . '%');
                        })
                        ->orWhere('custom_value1', 'like', '%' . $term . '%')
                        ->orWhere('custom_value2', 'like', '%' . $term . '%')
                        ->orWhere('custom_value3', 'like', '%' . $term . '%')
                        ->orWhere('custom_value4', 'like', '%' . $term . '%');
                });
            }
        });


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

        if (isset($sort_col[0]) && $sort_col[0] == 'documents') {
            return $this->builder;
        }

        if (isset($sort_col[0]) && $sort_col[0] == 'display_name') {
            $sort_col[0] = 'name';
        }

        if (is_array($sort_col) && in_array($sort_col[0], ['contacts', 'contact_email'])) {
        } elseif (!is_array($sort_col) || count($sort_col) != 2 || !in_array($sort_col[0], \Illuminate\Support\Facades\Schema::getColumnListing($this->builder->getModel()->getTable()))) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        if ($sort_col[0] == 'number') {
            return $this->builder->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . $dir);
        }

        if ($sort_col[0] == 'name') {
            // Use a raw subquery in the ORDER BY instead of adding it to SELECT
            // This avoids conflicts with the Excludable trait

            return $this->builder->orderByRaw(
                "
                COALESCE(
                    NULLIF(clients.name, ''), 
                    (
                        SELECT COALESCE(NULLIF(first_name, ''), email) 
                        FROM client_contacts 
                        WHERE client_contacts.client_id = clients.id 
                        AND client_contacts.deleted_at IS NULL 
                        LIMIT 1
                    )
                ) " . $dir
            );
        }


        if($sort_col[0] == 'contact_email') {
            return $this->builder->orderBy(\App\Models\ClientContact::select('email')
            ->whereColumn('client_contacts.client_id', 'clients.id')
            ->limit(1), $dir);
        }

        if ($sort_col[0] == 'contacts') {
            return $this->builder->orderByRaw(
                "
                (
                    SELECT 
                        CASE 
                            WHEN first_name IS NOT NULL AND first_name != '' AND last_name IS NOT NULL AND last_name != '' 
                            THEN CONCAT(first_name, ' ', last_name)
                            WHEN first_name IS NOT NULL AND first_name != '' 
                            THEN first_name
                            WHEN last_name IS NOT NULL AND last_name != '' 
                            THEN last_name
                            ELSE email
                        END
                    FROM client_contacts 
                    WHERE client_contacts.client_id = clients.id 
                    AND client_contacts.deleted_at IS NULL
                    ORDER BY
                        CASE 
                            WHEN first_name IS NOT NULL AND first_name != '' AND last_name IS NOT NULL AND last_name != '' THEN 1
                            WHEN first_name IS NOT NULL AND first_name != '' THEN 2
                            WHEN last_name IS NOT NULL AND last_name != '' THEN 3
                            ELSE 4
                        END,
                        first_name ASC,
                        last_name ASC,
                        email ASC
                    LIMIT 1
                ) " . $dir
            );
        }
        return $this->builder->orderBy($sort_col[0], $dir);
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

    public function filter_details(string $filter = '')
    {
        if ($filter == 'true') {
            return $this->builder->select('id', 'name', 'number', 'id_number');
        }

        return $this->builder;
    }
}
