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
 * @implements Arrayable<string, mixed>
 */
final readonly class PartyData implements Arrayable, JsonSerializable
{
    /**
     * @param array<string, mixed> $address
     * @param array<int, PublicIdentifierData> $publicIdentifiers
     */
    public function __construct(
        public ?string $companyName = null,
        public array $address = [],
        public array $publicIdentifiers = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            companyName: ReportDataValidator::assertOptionalString($data['companyName'] ?? null, 'party.companyName'),
            address: array_key_exists('address', $data)
                ? ReportDataValidator::assertArray($data['address'], 'party.address')
                : [],
            publicIdentifiers: self::publicIdentifiersFromArray($data['publicIdentifiers'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'companyName' => $this->companyName,
            'address' => $this->address,
            'publicIdentifiers' => array_map(
                static fn (PublicIdentifierData $publicIdentifier): array => $publicIdentifier->toArray(),
                $this->publicIdentifiers,
            ),
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<int, PublicIdentifierData>
     */
    private static function publicIdentifiersFromArray(mixed $data): array
    {
        return array_map(
            static fn (mixed $publicIdentifier): PublicIdentifierData => PublicIdentifierData::fromArray(
                ReportDataValidator::assertArray($publicIdentifier, 'party.publicIdentifiers.*'),
            ),
            ReportDataValidator::assertList($data, 'party.publicIdentifiers'),
        );
    }
}
