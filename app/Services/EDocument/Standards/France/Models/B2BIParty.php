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
namespace App\Services\EDocument\Standards\France\Models;

use Symfony\Component\Serializer\Attribute\SerializedPath;

class B2BIParty
{
    /**
     * @var array<int, mixed>|null
     */
    #[SerializedPath('[cac:PartyIdentification]')]
    public ?array $party_identifications = null;

    #[SerializedPath('[cac:PartyName][0][cbc:Name]')]
    public ?string $company_name = null;

    #[SerializedPath('[cac:PartyLegalEntity][0][cbc:RegistrationName]')]
    public ?string $registration_name = null;

    #[SerializedPath('[cac:PostalAddress][cbc:StreetName]')]
    public ?string $street1 = null;

    #[SerializedPath('[cac:PostalAddress][cbc:AdditionalStreetName]')]
    public ?string $street2 = null;

    #[SerializedPath('[cac:PostalAddress][cbc:CityName]')]
    public ?string $city = null;

    #[SerializedPath('[cac:PostalAddress][cbc:PostalZone]')]
    public ?string $zip = null;

    #[SerializedPath('[cac:PostalAddress][cac:Country][cbc:IdentificationCode][#]')]
    public ?string $country = null;

    #[SerializedPath('[cac:PartyTaxScheme]')]
    public ?array $tax_identifiers = null;

    #[SerializedPath('[cac:PartyLegalEntity][0][cbc:CompanyID][#]')]
    public ?string $legal_identifier = null;

    #[SerializedPath('[cac:PartyLegalEntity][0][cbc:CompanyID][@schemeID]')]
    public ?string $legal_identifier_scheme = null;

    public function __construct(
        ?array $party_identifications = null,
        ?string $company_name = null,
        ?string $registration_name = null,
        ?string $street1 = null,
        ?string $street2 = null,
        ?string $city = null,
        ?string $zip = null,
        ?string $country = null,
        ?array $tax_identifiers = null,
        ?string $legal_identifier = null,
        ?string $legal_identifier_scheme = null,
    ) {
        $this->party_identifications = $party_identifications;
        $this->company_name = $company_name;
        $this->registration_name = $registration_name;
        $this->street1 = $street1;
        $this->street2 = $street2;
        $this->city = $city;
        $this->zip = $zip;
        $this->country = $country;
        $this->tax_identifiers = $tax_identifiers;
        $this->legal_identifier = $legal_identifier;
        $this->legal_identifier_scheme = $legal_identifier_scheme;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'party' => array_filter([
                'companyName' => $this->company_name ?? $this->registration_name,
                'address' => array_filter([
                    'street1' => $this->street1,
                    'street2' => $this->street2,
                    'zip' => $this->zip,
                    'city' => $this->city,
                    'country' => $this->country,
                ], static fn (mixed $value): bool => ! is_null($value) && $value !== ''),
            ], static fn (mixed $value): bool => ! is_null($value) && $value !== []),
            'publicIdentifiers' => $this->publicIdentifiers(),
        ], static fn (mixed $value): bool => $value !== []);
    }

    /**
     * @return array<int, array{scheme: string, id: string}>
     */
    private function publicIdentifiers(): array
    {
        $identifiers = [];

        if (! is_null($this->legal_identifier)) {
            $identifiers[] = [
                'scheme' => $this->legalIdentifierScheme(),
                'id' => $this->legal_identifier,
            ];
        }

        foreach ($this->tax_identifiers ?? [] as $identifier) {
            $id = data_get($identifier, 'cbc:CompanyID.#');

            if (! is_string($id) || $id === '') {
                continue;
            }

            $identifiers[] = [
                'scheme' => $this->country.':VAT',
                'id' => $id,
            ];
        }

        return $identifiers;
    }

    private function legalIdentifierScheme(): string
    {
        if ($this->country === 'FR') {
            return 'FR:SIRENE';
        }

        return trim(($this->country ?? '').':'.($this->legal_identifier_scheme ?: 'ID'), ':');
    }
}
