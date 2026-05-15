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
        public int|float|string $taxableAmount,
        public int|float|string $taxAmount,
        public int|float|string $percent,
        public string $currency,
        public ?string $category = null,
        public ?string $exemptionReason = null,
        public ?string $exemptionReasonCode = null,
    ) {
        ReportDataValidator::assertNumeric($this->taxableAmount, 'taxSubtotals.taxableAmount');
        ReportDataValidator::assertNumeric($this->taxAmount, 'taxSubtotals.taxAmount');
        ReportDataValidator::assertNumeric($this->percent, 'taxSubtotals.percent');
        ReportDataValidator::assertNonEmptyString($this->currency, 'taxSubtotals.currency');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            taxableAmount: ReportDataValidator::assertNumeric($data['taxableAmount'] ?? null, 'taxSubtotals.taxableAmount'),
            taxAmount: ReportDataValidator::assertNumeric($data['taxAmount'] ?? null, 'taxSubtotals.taxAmount'),
            percent: ReportDataValidator::assertNumeric($data['percent'] ?? null, 'taxSubtotals.percent'),
            currency: ReportDataValidator::assertNonEmptyString($data['currency'] ?? null, 'taxSubtotals.currency'),
            category: ReportDataValidator::assertOptionalString($data['category'] ?? null, 'taxSubtotals.category'),
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
            'taxableAmount' => $this->taxableAmount,
            'taxAmount' => $this->taxAmount,
            'percent' => $this->percent,
            'currency' => $this->currency,
            'category' => $this->category,
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
