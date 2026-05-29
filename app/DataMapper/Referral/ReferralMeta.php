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

class ReferralMeta implements Arrayable, JsonSerializable
{
    public int $free = 0;

    public int $pro = 0;

    public int $enterprise = 0;

    public ?CalendarConnection $calendar_connection = null;

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

        $this->free = (int) ($entity['free'] ?? 0);
        $this->pro = (int) ($entity['pro'] ?? 0);
        $this->enterprise = (int) ($entity['enterprise'] ?? 0);
        $this->calendar_connection = $this->hydrateCalendarConnection($entity);
    }

    public function updateReferralCounts(int $free, int $pro, int $enterprise): self
    {
        $this->free = $free;
        $this->pro = $pro;
        $this->enterprise = $enterprise;

        return $this;
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
        $payload = [
            'free' => $this->free,
            'pro' => $this->pro,
            'enterprise' => $this->enterprise,
        ];

        if ($this->calendar_connection) {
            $payload['calendar_connection'] = $this->calendar_connection->toStorageArray();
        }

        return $payload;
    }

    /**
     * @return array{free: int, pro: int, enterprise: int, calendar_connection?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $payload = [
            'free' => $this->free,
            'pro' => $this->pro,
            'enterprise' => $this->enterprise,
        ];

        if ($this->calendar_connection) {
            $payload['calendar_connection'] = $this->calendar_connection->toArray();
        }

        return $payload;
    }

    public function toResponseObject(): \stdClass
    {
        $payload = new \stdClass();
        $payload->free = $this->free;
        $payload->pro = $this->pro;
        $payload->enterprise = $this->enterprise;

        if ($this->calendar_connection) {
            $payload->calendar_connection = $this->calendar_connection->toResponseObject();
        }

        return $payload;
    }

    /**
     * @return array{free: int, pro: int, enterprise: int, calendar_connection?: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<string, mixed> $entity
     */
    private function hydrateCalendarConnection(array $entity): ?CalendarConnection
    {
        if (isset($entity['calendar_connection']) && $entity['calendar_connection']) {
            return $entity['calendar_connection'] instanceof CalendarConnection
                ? $entity['calendar_connection']
                : new CalendarConnection($entity['calendar_connection']);
        }

        if (isset($entity['calendar']) && $entity['calendar']) {
            return $entity['calendar'] instanceof CalendarConnection
                ? $entity['calendar']
                : new CalendarConnection($entity['calendar']);
        }

        if (isset($entity['calendar_connections']) && is_array($entity['calendar_connections']) && $entity['calendar_connections']) {
            $firstConnection = array_values($entity['calendar_connections'])[0];

            return $firstConnection instanceof CalendarConnection
                ? $firstConnection
                : new CalendarConnection($firstConnection);
        }

        return null;
    }
}
