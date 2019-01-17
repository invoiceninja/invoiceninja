<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class ClientFilters extends QueryFilters
{

    /**
     * Filters by due_date
     *     
     * @param  string $due_date 
     * @return Builder 
     */
    public function balance($balance)
    {
        $parts = $this->split($balance);

        return $this->builder->where('balance', $parts->operator, $parts->value);
    }

    public function between_balance($balance)
    {
        $parts = explode(":", $balance);

        return $this->builder->whereBetween('balance', [$parts[0], $parts[1]]);
    }

    public function filter($filter)
    {
        return  $this->builder->where(function ($query) use ($filter) {
                    $this->builder->where('clients.name', 'like', '%'.$filter.'%')
                          ->orWhere('clients.id_number', 'like', '%'.$filter.'%')
                          ->orWhere('client_contacts.first_name', 'like', '%'.$filter.'%')
                          ->orWhere('client_contacts.last_name', 'like', '%'.$filter.'%')
                          ->orWhere('client_contacts.email', 'like', '%'.$filter.'%');
                });
    }

    public function active()
    {
        return $this->builder->orWhereNull('clients.deleted_at');
    }

    public function archived()
    {
        return $this->builder->orWhere(function ($query) {
                        $query->whereNotNull('clients.deleted_at');
                    });
    }

    public function deleted()
    {
         $this->builder->orWhere(function ($query) {
                $query->whereNotNull('clients.deleted_at')
                      ->where('clients.is_deleted', '=', 1);
            });
    }

    /**
     * Filter by popularity.
     *
     //* @param  string $order
     //* @return Builder
    
    public function popular($order = 'desc')
    {
        return $this->builder->orderBy('views', $order);
    }

    /**
     * Filter by difficulty.
     *
     * @param  string $level
     * @return Builder
     
    public function difficulty($level)
    {
        return $this->builder->where('difficulty', $level);
    }

    /**
     * Filter by length.
     *
     * @param  string $order
     * @return Builder
    
    public function length($order = 'asc')
    {
        return $this->builder->orderBy('length', $order);
    }

    */
}