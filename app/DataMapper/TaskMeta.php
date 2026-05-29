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

use App\Casts\TaskMetaCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class TaskMeta implements Arrayable, Castable, JsonSerializable
{
    public TaskNotification $notification;

    public function __construct(
        public string $calendar_event_id = '',
        ?TaskNotification $notification = null,
    ) {
        $this->notification = $notification ?? new TaskNotification();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return TaskMetaCast::class;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            calendar_event_id: (string) ($data['calendar_event_id'] ?? $data['event_id'] ?? ''),
            notification: TaskNotification::fromArray(self::arrayValue($data['notification'] ?? [])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'calendar_event_id' => $this->calendar_event_id,
            'notification' => $this->notification->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), true, 512, JSON_THROW_ON_ERROR);
        }

        return [];
    }
}