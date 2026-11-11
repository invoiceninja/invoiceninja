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
use InvalidArgumentException;
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

    /** @return array<string, mixed> */
    public function toB2BIPartyArray(): array
    {
        if ($this->publicIdentifiers !== []) {
            throw new InvalidArgumentException('B2Bi party identifiers must be supplied on the accounting party wrapper.');
        }

        $allowedAddressKeys = ['street1', 'street2', 'zip', 'city', 'country'];
        $unknownAddressKeys = array_diff(array_keys($this->address), $allowedAddressKeys);

        if ($unknownAddressKeys !== []) {
            throw new InvalidArgumentException('B2Bi party address contains unsupported fields: '.implode(', ', $unknownAddressKeys).'.');
        }

        $companyName = ReportDataValidator::assertNonEmptyString($this->companyName, 'b2biParty.companyName');
        $country = ReportDataValidator::assertCountryCode($this->address['country'] ?? null, 'b2biParty.address.country');

        return [
            'companyName' => $companyName,
            'address' => array_filter([
                'street1' => ReportDataValidator::assertOptionalString($this->address['street1'] ?? null, 'b2biParty.address.street1'),
                'street2' => ReportDataValidator::assertOptionalString($this->address['street2'] ?? null, 'b2biParty.address.street2'),
                'zip' => ReportDataValidator::assertOptionalString($this->address['zip'] ?? null, 'b2biParty.address.zip'),
                'city' => ReportDataValidator::assertOptionalString($this->address['city'] ?? null, 'b2biParty.address.city'),
                'country' => $country,
            ], static fn (mixed $value): bool => ! is_null($value)),
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
