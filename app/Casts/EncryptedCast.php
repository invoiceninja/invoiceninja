<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class EncryptedCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return is_string($value) && strlen($value) > 1 ? decrypt($value) : null;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return [$key => ! is_null($value) ? encrypt($value) : null];
    }
}
