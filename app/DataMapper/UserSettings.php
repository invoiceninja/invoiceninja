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

use App\DataMapper\Referral\CalendarConnection;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class UserSettings implements Arrayable, JsonSerializable
{
    public ?CalendarConnection $calendar_connection = null;

    /** @var array<string, mixed> */
    private array $extra = [];

    public function __construct(mixed $entity = null)
    {
        if (!$entity) {
            return;
        }

        $this->hydrate(is_object($entity) ? get_object_vars($entity) : $entity);
    }

    public function setCalendarConnection(CalendarConnection $calendarConnection): self
    {
        $this->calendar_connection = $calendarConnection;

        return $this;
    }

    public function clearCalendarConnection(): self
    {
        $this->calendar_connection = null;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toStorageArray(): array
    {
        $payload = $this->extra;

        if ($this->calendar_connection) {
            $payload['calendar_connection'] = $this->calendar_connection->toStorageArray();
        } else {
            unset($payload['calendar_connection']);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = $this->extra;

        if ($this->calendar_connection) {
            $payload['calendar_connection'] = $this->calendar_connection->toArray();
        } else {
            unset($payload['calendar_connection']);
        }

        return $payload;
    }

    public function toResponseObject(): \stdClass
    {
        return (object) [
            'calendar_connection' => $this->calendar_connection?->toResponseObject() ?? (object) [
                'status' => CalendarConnection::STATUS_DISCONNECTED,
                'email' => '',
            ],
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
     * @param array<string, mixed>|mixed $entity
     */
    private function hydrate(mixed $entity): void
    {
        if (!is_array($entity)) {
            return;
        }

        $this->extra = $entity;
        unset($this->extra['calendar_connection']);

        if (isset($entity['calendar_connection']) && $entity['calendar_connection']) {
            $this->calendar_connection = $entity['calendar_connection'] instanceof CalendarConnection
                ? $entity['calendar_connection']
                : new CalendarConnection($entity['calendar_connection']);
        }
    }
}