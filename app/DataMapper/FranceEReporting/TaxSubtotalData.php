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
final readonly class TaxSubtotalData implements Arrayable, JsonSerializable
{
    public function __construct(
        public int|float|string $percentage,
        public ?string $category = null,
        public int|float|string|null $taxableAmount = null,
        public int|float|string|null $taxAmount = null,
        public ?string $currency = null,
        public ?string $country = null,
        public int|float|string|null $amountIncludingTax = null,
        public ?string $exemptionReason = null,
        public ?string $exemptionReasonCode = null,
    ) {
        ReportDataValidator::assertNumeric($this->percentage, 'taxSubtotals.percentage');

        if (! is_null($this->taxableAmount)) {
            ReportDataValidator::assertNumeric($this->taxableAmount, 'taxSubtotals.taxableAmount');
        }

        if (! is_null($this->taxAmount)) {
            ReportDataValidator::assertNumeric($this->taxAmount, 'taxSubtotals.taxAmount');
        }

        if (! is_null($this->amountIncludingTax)) {
            ReportDataValidator::assertNumeric($this->amountIncludingTax, 'taxSubtotals.amountIncludingTax');
        }

        if (is_null($this->taxableAmount) !== is_null($this->taxAmount)) {
            throw new \InvalidArgumentException('taxSubtotals.taxableAmount and taxSubtotals.taxAmount must be provided together.');
        }

        if (is_null($this->taxableAmount) && is_null($this->amountIncludingTax)) {
            throw new \InvalidArgumentException('taxSubtotals requires taxableAmount/taxAmount or amountIncludingTax.');
        }

        if (! is_null($this->currency)) {
            ReportDataValidator::assertNonEmptyString($this->currency, 'taxSubtotals.currency');
        }

        if (! is_null($this->country)) {
            ReportDataValidator::assertNonEmptyString($this->country, 'taxSubtotals.country');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            percentage: ReportDataValidator::assertNumeric($data['percentage'] ?? $data['percent'] ?? null, 'taxSubtotals.percentage'),
            category: ReportDataValidator::assertOptionalString($data['category'] ?? null, 'taxSubtotals.category'),
            taxableAmount: array_key_exists('taxableAmount', $data) && ! is_null($data['taxableAmount'])
                ? ReportDataValidator::assertNumeric($data['taxableAmount'], 'taxSubtotals.taxableAmount')
                : null,
            taxAmount: array_key_exists('taxAmount', $data) && ! is_null($data['taxAmount'])
                ? ReportDataValidator::assertNumeric($data['taxAmount'], 'taxSubtotals.taxAmount')
                : null,
            currency: ReportDataValidator::assertOptionalString($data['currency'] ?? null, 'taxSubtotals.currency'),
            country: ReportDataValidator::assertOptionalString($data['country'] ?? null, 'taxSubtotals.country'),
            amountIncludingTax: array_key_exists('amountIncludingTax', $data) && ! is_null($data['amountIncludingTax'])
                ? ReportDataValidator::assertNumeric($data['amountIncludingTax'], 'taxSubtotals.amountIncludingTax')
                : null,
            exemptionReason: ReportDataValidator::assertOptionalString($data['exemptionReason'] ?? null, 'taxSubtotals.exemptionReason'),
            exemptionReasonCode: ReportDataValidator::assertOptionalString($data['exemptionReasonCode'] ?? null, 'taxSubtotals.exemptionReasonCode'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'category' => $this->category,
            'percentage' => $this->percentage,
            'taxableAmount' => $this->taxableAmount,
            'taxAmount' => $this->taxAmount,
            'currency' => $this->currency,
            'country' => $this->country,
            'amountIncludingTax' => $this->amountIncludingTax,
            'exemptionReason' => $this->exemptionReason,
            'exemptionReasonCode' => $this->exemptionReasonCode,
        ], static fn (mixed $value): bool => ! is_null($value));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
