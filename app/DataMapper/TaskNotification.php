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

namespace App\DataMapper;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class TaskNotification implements Arrayable, JsonSerializable
{
    public function __construct(
        public bool $enabled = false,
        public ?int $notify_at = null,
        public ?int $triggered_by_user_id = null,
        public ?int $triggered_at = null,
        public ?int $sent_at = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: self::booleanValue($data['enabled'] ?? false),
            notify_at: self::nullableInteger($data['notify_at'] ?? null),
            triggered_by_user_id: self::nullableInteger($data['triggered_by_user_id'] ?? null),
            triggered_at: self::nullableInteger($data['triggered_at'] ?? null),
            sent_at: self::nullableInteger($data['sent_at'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'notify_at' => $this->notify_at,
            'triggered_by_user_id' => $this->triggered_by_user_id,
            'triggered_at' => $this->triggered_at,
            'sent_at' => $this->sent_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function booleanValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function nullableInteger(mixed $value): ?int
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        return (int) $value;
    }
}