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

use App\DataMapper\TaskMeta;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

class TaskMetaCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TaskMeta
    {
        if (is_null($value) || $value === '' || $value === '[]' || $value === '{}') {
            return null;
        }

        $meta = TaskMeta::fromArray($this->toArray($value, $key));

        return $meta->isEmpty() ? null : $meta;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_null($value)) {
            return [$key => null];
        }

        if (! $value instanceof TaskMeta) {
            $value = TaskMeta::fromArray($this->toArray($value, $key));
        }

        if ($value->isEmpty()) {
            return [$key => null];
        }

        return [
            $key => json_encode($value->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $value, string $key): array
    {
        if ($value instanceof TaskMeta) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException("Invalid {$key} JSON: {$exception->getMessage()}", 0, $exception);
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new InvalidArgumentException("{$key} must be a TaskMeta instance, array, object, JSON string, or null.");
    }
}