<?php

namespace App\Models\Traits;

use Illuminate\Contracts\Database\ModelIdentifier;

/**
 * Class SerialisesDeletedModels
 * @see https://github.com/laravel/framework/issues/9347#issuecomment-165647596
 */
trait SerialisesDeletedModels
{
    /**
     * @param $value
     * @return mixed
     */
    protected function getRestoredPropertyValue($value)
    {
        if (!$value instanceof ModelIdentifier) {
            return $value;
        }

        if (is_array($value->id)) {
            return $this->restoreCollection($value);
        }

        $instance = new $value->class;
        $query = $instance->newQuery()->useWritePdo();

        if (property_exists($instance, 'forceDeleting')) {
            return $query->withTrashed()->find($value->id);
        }

        return $query->findOrFail($value->id);
    }
}
