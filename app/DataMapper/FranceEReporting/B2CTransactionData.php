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
final readonly class B2CTransactionData implements Arrayable, JsonSerializable
{
    /**
     * @param array<int, TaxSubtotalData> $taxSubtotals
     */
    public function __construct(
        public string $date,
        public string $category,
        public string $currency,
        public int|float|string $amountExcludingVat,
        public int|float|string $amountIncludingVat,
        public int $transactionsCount,
        public ?string $vatPaymentOption = null,
        public array $taxSubtotals = [],
    ) {
        ReportDataValidator::assertDate($this->date, 'b2cTransactions.date');
        ReportDataValidator::assertNonEmptyString($this->category, 'b2cTransactions.category');
        ReportDataValidator::assertNonEmptyString($this->currency, 'b2cTransactions.currency');
        ReportDataValidator::assertNumeric($this->amountExcludingVat, 'b2cTransactions.amountExcludingVat');
        ReportDataValidator::assertNumeric($this->amountIncludingVat, 'b2cTransactions.amountIncludingVat');
        ReportDataValidator::assertPositiveInteger($this->transactionsCount, 'b2cTransactions.transactionsCount');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: ReportDataValidator::assertDate($data['date'] ?? null, 'b2cTransactions.date'),
            category: ReportDataValidator::assertNonEmptyString($data['category'] ?? null, 'b2cTransactions.category'),
            currency: ReportDataValidator::assertNonEmptyString($data['currency'] ?? null, 'b2cTransactions.currency'),
            amountExcludingVat: ReportDataValidator::assertNumeric($data['amountExcludingVat'] ?? null, 'b2cTransactions.amountExcludingVat'),
            amountIncludingVat: ReportDataValidator::assertNumeric($data['amountIncludingVat'] ?? null, 'b2cTransactions.amountIncludingVat'),
            transactionsCount: ReportDataValidator::assertPositiveInteger($data['transactionsCount'] ?? null, 'b2cTransactions.transactionsCount'),
            vatPaymentOption: ReportDataValidator::assertOptionalString($data['vatPaymentOption'] ?? null, 'b2cTransactions.vatPaymentOption'),
            taxSubtotals: self::taxSubtotalsFromArray($data['taxSubtotals'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'date' => $this->date,
            'category' => $this->category,
            'currency' => $this->currency,
            'amountExcludingVat' => ReportDataValidator::numericValue($this->amountExcludingVat, 'b2cTransactions.amountExcludingVat'),
            'amountIncludingVat' => ReportDataValidator::numericValue($this->amountIncludingVat, 'b2cTransactions.amountIncludingVat'),
            'transactionsCount' => $this->transactionsCount,
            'vatPaymentOption' => $this->vatPaymentOption,
            'taxSubtotals' => array_values(array_map(
                static fn (TaxSubtotalData $taxSubtotal): array => $taxSubtotal->toArray(),
                $this->taxSubtotals,
            )),
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
     * @return array<int, TaxSubtotalData>
     */
    private static function taxSubtotalsFromArray(mixed $data): array
    {
        return array_map(
            static fn (mixed $taxSubtotal): TaxSubtotalData => TaxSubtotalData::fromArray(
                ReportDataValidator::assertArray($taxSubtotal, 'b2cTransactions.taxSubtotals.*'),
            ),
            ReportDataValidator::assertList($data, 'b2cTransactions.taxSubtotals'),
        );
    }
}
