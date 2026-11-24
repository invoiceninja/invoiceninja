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

use App\Casts\QuoteBackupCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class QuoteBackup implements Arrayable, Castable, JsonSerializable
{
    public const STATE_PENDING_ACCEPTANCE = 'pending_acceptance';
    public const STATE_ACCEPTED = 'accepted';

    /**
     * @param array<string, array<string, mixed>> $rules Configuration for deposit, optional items, acceptance, payment, etc.
     * @param list<string> $steps Ordered workflow steps; storing these does not execute them.
     * @param array<string, mixed>|null $option_selection Confirmed scope and its original offered lines.
     */
    public function __construct(
        public int $schema_version = 1,
        public array $rules = [],
        public array $steps = [],
        public string $guid = '',
        public ?array $option_selection = null,
    ) {}

    public static function castUsing(array $arguments): string
    {
        return QuoteBackupCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            schema_version: (int) ($data['schema_version'] ?? 1),
            rules: $data['rules'] ?? [],
            steps: $data['steps'] ?? [],
            guid: (string) ($data['guid'] ?? ''),
            option_selection: $data['option_selection'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [
            'schema_version' => $this->schema_version,
            'rules' => $this->rules,
            'steps' => $this->steps,
            'guid' => $this->guid,
        ];

        if ($this->option_selection !== null) {
            $data['option_selection'] = $this->option_selection;
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function forClone(): self
    {
        return new self(
            schema_version: $this->schema_version,
            rules: $this->rules,
            steps: $this->steps,
        );
    }
}
