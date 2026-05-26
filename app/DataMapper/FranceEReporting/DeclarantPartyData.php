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
final readonly class DeclarantPartyData implements Arrayable, JsonSerializable
{
    /**
     * @param array<int, PublicIdentifierData> $publicIdentifiers
     */
    public function __construct(
        public ?PartyData $party = null,
        public array $publicIdentifiers = [],
    ) {
        if ($this->publicIdentifiers === []) {
            throw new \InvalidArgumentException('declarantParty.publicIdentifiers requires at least one item.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            party: array_key_exists('party', $data) && ! is_null($data['party'])
                ? PartyData::fromArray(ReportDataValidator::assertArray($data['party'], 'declarantParty.party'))
                : null,
            publicIdentifiers: self::publicIdentifiersFromArray($data['publicIdentifiers'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'party' => $this->party?->toArray(),
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
                ReportDataValidator::assertArray($publicIdentifier, 'declarantParty.publicIdentifiers.*'),
            ),
            ReportDataValidator::assertList($data, 'declarantParty.publicIdentifiers'),
        );
    }
}
