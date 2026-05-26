<?php

namespace App\Services\EDocument\Standards\France\Models;

use Symfony\Component\Serializer\Attribute\SerializedPath;

class B2BITaxSubtotal
{
    #[SerializedPath('[cbc:TaxableAmount][#]')]
    public int|float|string|null $taxable_amount = null;

    #[SerializedPath('[cbc:TaxAmount][#]')]
    public int|float|string|null $tax_amount = null;

    #[SerializedPath('[cac:TaxCategory][cbc:Percent]')]
    public int|float|string|null $percentage = null;

    #[SerializedPath('[cac:TaxCategory][cbc:ID][#]')]
    public ?string $category = null;

    #[SerializedPath('[cac:TaxCategory][cac:TaxScheme][cbc:ID][#]')]
    public ?string $type = null;

    public function __construct(
        int|float|string|null $taxable_amount = null,
        int|float|string|null $tax_amount = null,
        int|float|string|null $percentage = null,
        ?string $category = null,
        ?string $type = null,
    ) {
        $this->taxable_amount = $taxable_amount;
        $this->tax_amount = $tax_amount;
        $this->percentage = $percentage;
        $this->category = $category;
        $this->type = $type;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $country = null): array
    {
        return array_filter([
            'taxCategory' => self::normalizeTaxCategory($this->category),
            'percentage' => $this->percentage,
            'taxableAmount' => $this->taxable_amount,
            'taxAmount' => $this->tax_amount,
            'country' => $country,
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== []);
    }

    public static function normalizeTaxCategory(?string $category): ?string
    {
        return match ($category) {
            'S' => 'standard',
            'K' => 'reverse_charge',
            'E' => 'exempt',
            'Z' => 'zero_rated',
            default => $category,
        };
    }
}
