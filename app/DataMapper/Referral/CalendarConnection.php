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

namespace App\DataMapper\Referral;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Throwable;

class CalendarConnection implements Arrayable, JsonSerializable
{
    public const PROVIDER_GOOGLE = 'google';

    public const PROVIDER_MICROSOFT = 'microsoft';

    public const STATUS_CONNECTED = 'CONNECTED';

    public const STATUS_DISCONNECTED = 'DISCONNECTED';

    public ?string $provider = null;

    public ?string $provider_user_id = null;

    public ?string $email = null;

    public ?string $access_token = null;

    public ?string $refresh_token = null;

    public ?int $expires_at = null;

    /** @var array<int, array{calendar_id: string, name?: string, primary?: bool, writable?: bool}> */
    public array $calendars = [];

    public function __construct(mixed $entity = null)
    {
        if (!$entity) {
            return;
        }

        $this->hydrate(is_object($entity) ? get_object_vars($entity) : $entity);
    }

    /**
     * @param array<string, mixed>|mixed $entity
     */
    private function hydrate(mixed $entity): void
    {
        if (!is_array($entity)) {
            return;
        }

        $this->provider = $this->nullableString($entity['provider'] ?? null);
        $this->provider_user_id = $this->nullableString($entity['provider_user_id'] ?? $entity['oauth_user_id'] ?? null);
        $this->email = $this->nullableString($entity['email'] ?? null);
        $this->access_token = $this->decryptSensitiveValue($this->nullableString($entity['access_token'] ?? null));
        $this->refresh_token = $this->decryptSensitiveValue($this->nullableString($entity['refresh_token'] ?? null));
        $this->expires_at = $this->nullableInt($entity['expires_at'] ?? null);
        $this->calendars = $this->normalizeCalendars($entity['calendars'] ?? []);
    }

    public function isConnected(): bool
    {
        return (bool) $this->provider
            && (bool) $this->provider_user_id
            && (bool) $this->refresh_token;
    }

    public function tokenExpiresWithin(int $seconds = 300): bool
    {
        if (!$this->expires_at) {
            return true;
        }

        return $this->expires_at <= (time() + $seconds);
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorageArray(): array
    {
        return $this->withoutEmptyValues([
            'provider' => $this->provider,
            'provider_user_id' => $this->provider_user_id,
            'email' => $this->email,
            'access_token' => $this->encryptSensitiveValue($this->access_token),
            'refresh_token' => $this->encryptSensitiveValue($this->refresh_token),
            'expires_at' => $this->expires_at,
            'calendars' => $this->calendars,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->withoutEmptyValues([
            'connected' => $this->isConnected(),
            'provider' => $this->provider,
            'provider_user_id' => $this->provider_user_id,
            'email' => $this->email,
            'expires_at' => $this->expires_at,
            'calendars' => $this->calendars,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        return $this->toArray();
    }

    public function toResponseObject(): \stdClass
    {
        return (object) [
            'status' => $this->status(),
            'email' => (string) $this->email,
        ];
    }

    public function status(): string
    {
        return $this->isConnected() ? self::STATUS_CONNECTED : self::STATUS_DISCONNECTED;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<int, array{calendar_id: string, name?: string, primary?: bool, writable?: bool}>
     */
    private function normalizeCalendars(mixed $calendars): array
    {
        if (!is_array($calendars)) {
            return [];
        }

        return collect($calendars)
            ->map(fn (mixed $calendar): array => $this->normalizeCalendar($calendar))
            ->filter(fn (array $calendar): bool => isset($calendar['calendar_id']) && $calendar['calendar_id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{calendar_id?: string, name?: string, primary?: bool, writable?: bool}
     */
    private function normalizeCalendar(mixed $calendar): array
    {
        $payload = is_object($calendar) ? get_object_vars($calendar) : $calendar;

        if (!is_array($payload)) {
            return [];
        }

        return $this->withoutEmptyValues([
            'calendar_id' => $this->nullableString($payload['calendar_id'] ?? $payload['id'] ?? null),
            'name' => $this->nullableString($payload['name'] ?? $payload['summary'] ?? null),
            'primary' => $this->nullableBool($payload['primary'] ?? $payload['isDefaultCalendar'] ?? null),
            'writable' => $this->calendarIsWritable($payload),
        ]);
    }

    /**
     * @param array<string, mixed> $calendar
     */
    private function calendarIsWritable(array $calendar): ?bool
    {
        if (array_key_exists('writable', $calendar)) {
            return $this->nullableBool($calendar['writable']);
        }

        if (array_key_exists('canEdit', $calendar)) {
            return $this->nullableBool($calendar['canEdit']);
        }

        if (array_key_exists('accessRole', $calendar)) {
            return in_array($calendar['accessRole'], ['owner', 'writer'], true);
        }

        return null;
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function encryptSensitiveValue(?string $value): ?string
    {
        if (!$value) {
            return $value;
        }

        return encrypt($value);
    }

    private function decryptSensitiveValue(?string $value): ?string
    {
        if (!$value) {
            return $value;
        }

        try {
            return decrypt($value);
        } catch (Throwable) {
            return $value;
        }
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function withoutEmptyValues(array $values): array
    {
        return array_filter(
            $values,
            fn ($value): bool => $value !== null && $value !== '' && $value !== []
        );
    }
}
