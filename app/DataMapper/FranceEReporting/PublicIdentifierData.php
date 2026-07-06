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

namespace App\DataMapper\FranceEReporting;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, string>
 */
final readonly class PublicIdentifierData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $scheme,
        public string $id,
    ) {
        ReportDataValidator::assertNonEmptyString($this->scheme, 'publicIdentifiers.scheme');
        ReportDataValidator::assertNonEmptyString($this->id, 'publicIdentifiers.id');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheme: ReportDataValidator::assertNonEmptyString($data['scheme'] ?? null, 'publicIdentifiers.scheme'),
            id: ReportDataValidator::assertNonEmptyString($data['id'] ?? null, 'publicIdentifiers.id'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'id' => $this->id,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
