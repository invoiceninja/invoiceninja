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

namespace App\Models\Traits;

trait Excludable
{
    /**
     * Get the array of columns
     *
     * @return mixed
     */
    private function getTableColumns()
    {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**
     * Exclude an array of elements from the result.
     * @param Builder $query
     * @param array $columns
     *
     * @return mixed
     */
    public function scopeExclude($query, $columns): \Illuminate\Database\Eloquent\Builder
    {
        return $query->select(array_diff($this->getTableColumns(), (array) $columns));
    }
}
