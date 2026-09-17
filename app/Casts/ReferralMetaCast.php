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

namespace App\Casts;

use App\DataMapper\Referral\ReferralMeta;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

class ReferralMetaCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ReferralMeta
    {
        if (!$value || (is_string($value) && $value === 'null')) {
            return new ReferralMeta();
        }

        if ($value instanceof ReferralMeta) {
            return $value;
        }

        try {
            $payload = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
        } catch (JsonException) {
            $payload = [];
        }

        return new ReferralMeta($payload);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $meta = $value instanceof ReferralMeta ? $value : new ReferralMeta($value);

        return json_encode($meta->toArray(), JSON_THROW_ON_ERROR);
    }
}
