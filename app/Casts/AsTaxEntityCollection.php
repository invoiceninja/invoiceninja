<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use App\DataMapper\EInvoice\TaxEntity;

class AsTaxEntityCollection implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (!$value || (is_string($value) && $value == "null")) {
            return [];
        }

        $items = json_decode($value, true);

        return array_map(fn ($item) => new TaxEntity($item), $items);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (!$value) {
            return '[]';
        }

        if ($value instanceof TaxEntity) {
            $value = [$value];
        }

        return json_encode(array_map(fn ($entity) => get_object_vars($entity), $value));
    }
}
