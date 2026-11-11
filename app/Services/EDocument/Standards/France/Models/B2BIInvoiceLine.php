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
use App\DataMapper\FranceEReporting\ReportDataValidator;

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

    #[SerializedPath('[cbc:CreditedQuantity][#]')]
    public int|float|string|null $credited_quantity = null;

    #[SerializedPath('[cbc:CreditedQuantity][@unitCode]')]
    public ?string $credited_quantity_unit_code = null;

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
        int|float|string|null $credited_quantity = null,
        ?string $credited_quantity_unit_code = null,
        int|float|string|null $amount_excluding_tax = null,
        ?array $taxes = null,
    ) {
        $this->id = $id;
        $this->description = $description;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->quantity_unit_code = $quantity_unit_code;
        $this->credited_quantity = $credited_quantity;
        $this->credited_quantity_unit_code = $credited_quantity_unit_code;
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
            'amountExcludingVat' => is_null($this->amount_excluding_tax) ? null : ReportDataValidator::numericValue($this->amount_excluding_tax, 'b2biInvoices.invoiceLines.amountExcludingVat'),
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
            'percentage' => is_null(data_get($tax, 'cbc:Percent')) ? null : ReportDataValidator::numericValue(data_get($tax, 'cbc:Percent'), 'b2biInvoices.invoiceLines.tax.percentage'),
            'category' => B2BITaxSubtotal::normalizeTaxCategory(data_get($tax, 'cbc:ID.#')),
            'country' => $country,
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== '');
    }
}
