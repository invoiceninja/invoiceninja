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