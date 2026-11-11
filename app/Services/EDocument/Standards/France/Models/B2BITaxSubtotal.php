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
use App\DataMapper\FranceEReporting\ReportDataValidator;
use App\Services\EDocument\Standards\France\FranceEReportTaxCategory;

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
            'percentage' => is_null($this->percentage) ? null : ReportDataValidator::numericValue($this->percentage, 'b2biInvoices.taxSubtotals.percentage'),
            'taxableAmount' => is_null($this->taxable_amount) ? null : ReportDataValidator::numericValue($this->taxable_amount, 'b2biInvoices.taxSubtotals.taxableAmount'),
            'taxAmount' => is_null($this->tax_amount) ? null : ReportDataValidator::numericValue($this->tax_amount, 'b2biInvoices.taxSubtotals.taxAmount'),
            'country' => $country,
        ], static fn (mixed $value): bool => ! is_null($value));
    }

    public static function normalizeTaxCategory(?string $category): ?string
    {
        return FranceEReportTaxCategory::normalize($category);
    }
}
