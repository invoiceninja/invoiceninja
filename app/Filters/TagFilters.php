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

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TagFilters extends QueryFilters
{
    /**
     * Search by tag name.
     */
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->where(function ($query) use ($filter) {
            $query->where('name', 'like', '%'.$filter.'%');
        });
    }

    /**
     * Constrain results to a single taggable entity_type (FQCN).
     *
     * Tags are polymorphic by design; consumers should always provide the
     * relevant entity_type so a picker for one entity never bleeds tags
     * from another.
     */
    public function entity_type(string $entity_type = ''): Builder
    {

        $entity_type = Tag::normalizeEntityType($entity_type);

        if ($entity_type === null) {
            return $this->builder;
        }

        return $this->builder->whereIn('entity_type', [$entity_type, Tag::GLOBAL_ENTITY_TYPE]);
    }

    public function entity_types(string $entity_types = ''): Builder
    {
        $entity_types = explode(',', $entity_types);

        $entity_types = collect($entity_types)->map(function ($type){
                            return Tag::normalizeEntityType($type);
                        })
                        ->filter()
                        ->values()
                        ->toArray();

        return $this->builder->whereIn('entity_type', $entity_types);
    }

    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);

        if (count($sort_col) != 2 || !in_array($sort_col[0], Schema::getColumnListing('tags'))) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        return $this->builder->orderBy($sort_col[0], $dir);
    }

    public function entityFilter(): Builder
    {
        return $this->builder
            ->company()
            ->whereNull('tags.deleted_at')
            ->where('tags.is_deleted', false);
    }
}
