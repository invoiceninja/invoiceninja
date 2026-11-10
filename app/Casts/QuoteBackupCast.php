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

use App\DataMapper\QuoteBackup;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class QuoteBackupCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?QuoteBackup
    {
        return is_null($value) ? null : QuoteBackup::fromArray($this->toArray($value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (is_null($value)) {
            return [$key => null];
        }

        if (! $value instanceof QuoteBackup) {
            $value = QuoteBackup::fromArray($this->toArray($value));
        }

        return [$key => json_encode($value->toArray(), JSON_THROW_ON_ERROR)];
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }

        if (is_string($value)) {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('Quote backup must be a QuoteBackup instance, array, object, JSON string, or null.');
        }

        return $value;
    }
}
