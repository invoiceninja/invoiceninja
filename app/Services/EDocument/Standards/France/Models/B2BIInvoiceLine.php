<?php

namespace App\Services\EDocument\Standards\France\Models;

use Symfony\Component\Serializer\Attribute\SerializedPath;

class B2BIInvoiceLine
{
    #[SerializedPath('[cbc:ID][#]')]
    public ?string $id = null;

    #[SerializedPath('[cac:Item][cbc:Description]')]
    public ?string $description = null;

    #[SerializedPath('[cac:Item][cbc:Name]')]
    public ?string $name = null;

    #[SerializedPath('[cbc:InvoicedQuantity][#]')]
    public int|float|string|null $quantity = null;

    #[SerializedPath('[cbc:InvoicedQuantity][@unitCode]')]
    public ?string $quantity_unit_code = null;

    #[SerializedPath('[cbc:LineExtensionAmount][#]')]
    public int|float|string|null $amount_excluding_tax = null;

    /**
     * @var array<int, mixed>|null
     */
    #[SerializedPath('[cac:Item][cac:ClassifiedTaxCategory]')]
    public ?array $taxes = null;

    public function __construct(
        ?string $id = null,
        ?string $description = null,
        ?string $name = null,
        int|float|string|null $quantity = null,
        ?string $quantity_unit_code = null,
        int|float|string|null $amount_excluding_tax = null,
        ?array $taxes = null,
    ) {
        $this->id = $id;
        $this->description = $description;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->quantity_unit_code = $quantity_unit_code;
        $this->amount_excluding_tax = $amount_excluding_tax;
        $this->taxes = $taxes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $country = null): array
    {
        return array_filter([
            'description' => $this->description ?? $this->name,
            'amountExcludingVat' => $this->amount_excluding_tax,
            'tax' => $this->taxToArray($country),
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function taxToArray(?string $country): ?array
    {
        $tax = $this->taxes[0] ?? null;

        if (! is_array($tax)) {
            return null;
        }

        return array_filter([
            'percentage' => data_get($tax, 'cbc:Percent'),
            'category' => B2BITaxSubtotal::normalizeTaxCategory(data_get($tax, 'cbc:ID.#')),
            'country' => $country,
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== '');
    }
}
