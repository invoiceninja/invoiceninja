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

use App\DataMapper\UserSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

class UserSettingsCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): UserSettings
    {
        if (!$value || (is_string($value) && $value === 'null')) {
            return new UserSettings();
        }

        if ($value instanceof UserSettings) {
            return $value;
        }

        try {
            $payload = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
        } catch (JsonException) {
            $payload = [];
        }

        return new UserSettings($payload);
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

        $settings = $value instanceof UserSettings ? $value : new UserSettings($value);
        $payload = $settings->toStorageArray();

        if ($payload === []) {
            return null;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}