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

namespace App\DataMapper\Billing;

use App\Casts\BillingContextCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class BillingContext implements Arrayable, Castable, JsonSerializable
{
    public const VERSION = 1;

    /**
     * @param array<string, mixed>|null $pending_change
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public int $version = self::VERSION,
        public ?int $client_id = null,
        public ?int $recurring_invoice_id = null,
        public string $current_plan_key = '',
        public string $term = '',
        public int $num_users = 0,
        public int $docuninja_users = 0,
        public ?string $plan_started = null,
        public ?string $plan_paid = null,
        public ?string $plan_expires = null,
        public ?string $last_quote_id = null,
        public ?array $pending_change = null,
        public array $extra = [],
    ) {}

    /**
     * @param array<string, mixed> $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return BillingContextCast::class;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $known_keys = [
            'version',
            'client_id',
            'recurring_invoice_id',
            'current_plan_key',
            'plan',
            'term',
            'plan_term',
            'num_users',
            'docuninja_users',
            'docuninja_num_users',
            'plan_started',
            'plan_paid',
            'plan_expires',
            'last_quote_id',
            'quote_id',
            'pending_change',
            'extra',
        ];

        $extra = array_diff_key($data, array_flip($known_keys));

        if (isset($data['extra']) && is_array($data['extra'])) {
            $extra = array_merge($extra, $data['extra']);
        }

        return new self(
            version: self::nullableInt($data['version'] ?? null) ?: self::VERSION,
            client_id: self::nullableInt($data['client_id'] ?? null),
            recurring_invoice_id: self::nullableInt($data['recurring_invoice_id'] ?? null),
            current_plan_key: (string) ($data['current_plan_key'] ?? $data['plan'] ?? ''),
            term: (string) ($data['term'] ?? $data['plan_term'] ?? ''),
            num_users: (int) ($data['num_users'] ?? 0),
            docuninja_users: (int) ($data['docuninja_users'] ?? $data['docuninja_num_users'] ?? 0),
            plan_started: self::nullableString($data['plan_started'] ?? null),
            plan_paid: self::nullableString($data['plan_paid'] ?? null),
            plan_expires: self::nullableString($data['plan_expires'] ?? null),
            last_quote_id: self::nullableString($data['last_quote_id'] ?? $data['quote_id'] ?? null),
            pending_change: self::nullableArray($data['pending_change'] ?? null),
            extra: $extra,
        );
    }

    public function hasRecurringInvoice(): bool
    {
        return $this->recurring_invoice_id !== null;
    }

    public function setRecurringInvoiceId(?int $recurring_invoice_id): self
    {
        $this->recurring_invoice_id = $recurring_invoice_id;

        return $this;
    }

    public function clearRecurringInvoiceId(): self
    {
        $this->recurring_invoice_id = null;

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->client_id === null
            && $this->recurring_invoice_id === null
            && $this->current_plan_key === ''
            && $this->term === ''
            && $this->num_users === 0
            && $this->docuninja_users === 0
            && $this->plan_started === null
            && $this->plan_paid === null
            && $this->plan_expires === null
            && $this->last_quote_id === null
            && $this->pending_change === null
            && $this->extra === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = array_merge($this->extra, [
            'version' => $this->version,
            'client_id' => $this->client_id,
            'recurring_invoice_id' => $this->recurring_invoice_id,
            'current_plan_key' => $this->current_plan_key,
            'term' => $this->term,
            'num_users' => $this->num_users,
            'docuninja_users' => $this->docuninja_users,
            'plan_started' => $this->plan_started,
            'plan_paid' => $this->plan_paid,
            'plan_expires' => $this->plan_expires,
            'last_quote_id' => $this->last_quote_id,
            'pending_change' => $this->pending_change,
        ]);

        return array_filter($payload, static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function nullableArray(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return $value;
    }
}
