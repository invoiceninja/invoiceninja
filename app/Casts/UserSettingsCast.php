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

        $settingsValue = $value instanceof UserSettings ? $value : $this->storageInputPayload($value);
        $settings = $settingsValue instanceof UserSettings ? $settingsValue : new UserSettings($settingsValue);
        $payload = $settings->toStorageArray();

        if ($this->hasResponseOnlyCalendarConnection($settingsValue)) {
            $existingPayload = $this->decodePayload($attributes[$key] ?? null);

            if (isset($existingPayload['calendar_connection']) && is_array($existingPayload['calendar_connection'])) {
                $payload['calendar_connection'] = $existingPayload['calendar_connection'];
            }
        }

        if ($payload === []) {
            return null;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function storageInputPayload(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->decodePayload($value);
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $value): array
    {
        if (!$value || (is_string($value) && $value === 'null')) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        try {
            $payload = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function hasResponseOnlyCalendarConnection(mixed $value): bool
    {
        $payload = is_object($value) ? get_object_vars($value) : $value;

        if (!is_array($payload) || !array_key_exists('calendar_connection', $payload)) {
            return false;
        }

        $connection = is_object($payload['calendar_connection'])
            ? get_object_vars($payload['calendar_connection'])
            : $payload['calendar_connection'];

        if (!is_array($connection)) {
            return false;
        }

        if ($this->hasNonEmptyToken($connection['access_token'] ?? null)
            || $this->hasNonEmptyToken($connection['refresh_token'] ?? null)) {
            return false;
        }

        return isset($connection['status'])
            || isset($connection['connected'])
            || isset($connection['provider'])
            || isset($connection['provider_user_id'])
            || isset($connection['expires_at'])
            || isset($connection['calendars']);
    }

    private function hasNonEmptyToken(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
