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

use Illuminate\Support\Facades\Schema;

/**
 * @method static Builder scopeExclude(array $columns)
 * @method static Builder exclude(array $columns)
 * @template TModelClass of \Illuminate\Database\Eloquent\Model
 * @extends \Illuminate\Database\Eloquent\Builder<TModelClass>
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
trait Excludable
{
    /**
     * Get the array of columns
     *
     * @return mixed
     */
    private function getTableColumns()
    {
        /** @var Schema|\App\Models\BaseModel $this */
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**
     * Exclude an array of elements from the result.
     *
     * @method static \Illuminate\Database\Eloquent\Builder<static> exclude($columns)
     * @method static \Illuminate\Database\Eloquent\Builder<static> exclude($columns)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExclude($query, $columns): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \Illuminate\Database\Eloquent\Builder|static $query */
        return $query->select(array_diff($this->getTableColumns(), (array) $columns));
    }
}
