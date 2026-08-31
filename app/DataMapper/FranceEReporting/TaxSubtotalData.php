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

use App\Services\EDocument\Standards\France\FranceEReportTaxCategory;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class TaxSubtotalData implements Arrayable, JsonSerializable
{
    public function __construct(
        public int|float|string $percentage,
        public ?string $taxCategory = null,
        public ?string $category = null,
        public int|float|string|null $taxableAmount = null,
        public int|float|string|null $taxAmount = null,
        public ?string $currency = null,
        public ?string $country = null,
        public int|float|string|null $amountIncludingTax = null,
        public int|float|string|null $amount = null,
        public ?string $exemptionReason = null,
        public ?string $exemptionReasonCode = null,
    ) {
        ReportDataValidator::assertPercentage($this->percentage, 'taxSubtotals.percentage');

        if (! is_null($this->taxableAmount)) {
            ReportDataValidator::assertCurrencyAmount($this->taxableAmount, 'taxSubtotals.taxableAmount');
        }

        if (! is_null($this->taxAmount)) {
            ReportDataValidator::assertCurrencyAmount($this->taxAmount, 'taxSubtotals.taxAmount');
        }

        if (! is_null($this->amountIncludingTax)) {
            ReportDataValidator::assertCurrencyAmount($this->amountIncludingTax, 'taxSubtotals.amountIncludingTax');
        }

        if (! is_null($this->amount)) {
            ReportDataValidator::assertCurrencyAmount($this->amount, 'taxSubtotals.amount');
        }

        if (! is_null($this->taxCategory)) {
            ReportDataValidator::assertNonEmptyString($this->taxCategory, 'taxSubtotals.taxCategory');

            if (! FranceEReportTaxCategory::isSupported($this->taxCategory)) {
                throw new InvalidArgumentException('taxSubtotals.taxCategory is not supported for France e-reporting.');
            }
        }

        if (! is_null($this->category)) {
            ReportDataValidator::assertNonEmptyString($this->category, 'taxSubtotals.category');

            if (! FranceEReportTaxCategory::isSupported($this->category)) {
                throw new InvalidArgumentException('taxSubtotals.category is not supported for France e-reporting.');
            }
        }

        if (is_null($this->taxableAmount) !== is_null($this->taxAmount)) {
            throw new \InvalidArgumentException('taxSubtotals.taxableAmount and taxSubtotals.taxAmount must be provided together.');
        }

        if (is_null($this->taxableAmount) && is_null($this->amountIncludingTax) && is_null($this->amount)) {
            throw new \InvalidArgumentException('taxSubtotals requires taxableAmount/taxAmount, amountIncludingTax, or amount.');
        }

        if (! is_null($this->currency)) {
            ReportDataValidator::assertNonEmptyString($this->currency, 'taxSubtotals.currency');
        }

        if (! is_null($this->country)) {
            ReportDataValidator::assertCountryCode($this->country, 'taxSubtotals.country');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            percentage: ReportDataValidator::assertNumeric($data['percentage'] ?? $data['percent'] ?? null, 'taxSubtotals.percentage'),
            taxCategory: ReportDataValidator::assertOptionalString($data['taxCategory'] ?? null, 'taxSubtotals.taxCategory'),
            category: ReportDataValidator::assertOptionalString($data['category'] ?? null, 'taxSubtotals.category'),
            taxableAmount: array_key_exists('taxableAmount', $data) && ! is_null($data['taxableAmount'])
                ? ReportDataValidator::assertCurrencyAmount($data['taxableAmount'], 'taxSubtotals.taxableAmount')
                : null,
            taxAmount: array_key_exists('taxAmount', $data) && ! is_null($data['taxAmount'])
                ? ReportDataValidator::assertCurrencyAmount($data['taxAmount'], 'taxSubtotals.taxAmount')
                : null,
            currency: ReportDataValidator::assertOptionalString($data['currency'] ?? null, 'taxSubtotals.currency'),
            country: ReportDataValidator::assertOptionalString($data['country'] ?? null, 'taxSubtotals.country'),
            amountIncludingTax: array_key_exists('amountIncludingTax', $data) && ! is_null($data['amountIncludingTax'])
                ? ReportDataValidator::assertCurrencyAmount($data['amountIncludingTax'], 'taxSubtotals.amountIncludingTax')
                : null,
            amount: array_key_exists('amount', $data) && ! is_null($data['amount'])
                ? ReportDataValidator::assertCurrencyAmount($data['amount'], 'taxSubtotals.amount')
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
            'taxCategory' => FranceEReportTaxCategory::normalize($this->taxCategory),
            'category' => FranceEReportTaxCategory::normalize($this->category),
            'percentage' => ReportDataValidator::numericValue($this->percentage, 'taxSubtotals.percentage'),
            'taxableAmount' => is_null($this->taxableAmount) ? null : ReportDataValidator::numericValue($this->taxableAmount, 'taxSubtotals.taxableAmount'),
            'taxAmount' => is_null($this->taxAmount) ? null : ReportDataValidator::numericValue($this->taxAmount, 'taxSubtotals.taxAmount'),
            'currency' => $this->currency,
            'country' => $this->country,
            'amountIncludingTax' => is_null($this->amountIncludingTax) ? null : ReportDataValidator::numericValue($this->amountIncludingTax, 'taxSubtotals.amountIncludingTax'),
            'amount' => is_null($this->amount) ? null : ReportDataValidator::numericValue($this->amount, 'taxSubtotals.amount'),
            'exemptionReason' => $this->exemptionReason,
            'exemptionReasonCode' => $this->exemptionReasonCode,
        ], static fn (mixed $value): bool => ! is_null($value));
    }

    /** @return array<string, int|float|string> */
    public function toB2BITransactionArray(): array
    {
        $this->requireOnlyFields(
            ['percentage', 'taxCategory', 'taxableAmount', 'taxAmount', 'country'],
            'B2Bi transaction tax subtotal',
        );
        $this->requireFields(['taxCategory', 'taxableAmount', 'taxAmount', 'country'], 'B2Bi transaction tax subtotal');

        return [
            'taxCategory' => FranceEReportTaxCategory::normalize($this->taxCategory),
            'percentage' => ReportDataValidator::numericValue($this->percentage, 'taxSubtotals.percentage'),
            'taxableAmount' => ReportDataValidator::numericValue($this->taxableAmount, 'taxSubtotals.taxableAmount'),
            'taxAmount' => ReportDataValidator::numericValue($this->taxAmount, 'taxSubtotals.taxAmount'),
            'country' => $this->country,
        ];
    }

    /** @return array<string, int|float|string> */
    public function toB2CTransactionArray(): array
    {
        $this->requireOnlyFields(
            ['percentage', 'category', 'taxableAmount', 'taxAmount'],
            'B2C transaction tax subtotal',
        );
        $this->requireFields(['category', 'taxableAmount', 'taxAmount'], 'B2C transaction tax subtotal');

        return [
            'category' => FranceEReportTaxCategory::normalize($this->category),
            'percentage' => ReportDataValidator::numericValue($this->percentage, 'taxSubtotals.percentage'),
            'taxableAmount' => ReportDataValidator::numericValue($this->taxableAmount, 'taxSubtotals.taxableAmount'),
            'taxAmount' => ReportDataValidator::numericValue($this->taxAmount, 'taxSubtotals.taxAmount'),
        ];
    }

    /** @return array<string, int|float|string> */
    public function toB2BIPaymentArray(): array
    {
        $this->requireOnlyFields(
            ['percentage', 'category', 'currency', 'country', 'amountIncludingTax'],
            'B2Bi payment tax subtotal',
        );
        $this->requireFields(['category', 'currency', 'country', 'amountIncludingTax'], 'B2Bi payment tax subtotal');

        return [
            'percentage' => ReportDataValidator::numericValue($this->percentage, 'taxSubtotals.percentage'),
            'category' => FranceEReportTaxCategory::normalize($this->category),
            'currency' => $this->currency,
            'country' => $this->country,
            'amountIncludingTax' => ReportDataValidator::numericValue($this->amountIncludingTax, 'taxSubtotals.amountIncludingTax'),
        ];
    }

    /** @return array<string, int|float|string> */
    public function toB2CPaymentArray(): array
    {
        $this->requireOnlyFields(
            ['percentage', 'category', 'currency', 'country', 'amount'],
            'B2C payment tax subtotal',
        );
        $this->requireFields(['category', 'currency', 'country', 'amount'], 'B2C payment tax subtotal');

        return [
            'category' => FranceEReportTaxCategory::normalize($this->category),
            'percentage' => ReportDataValidator::numericValue($this->percentage, 'taxSubtotals.percentage'),
            'country' => $this->country,
            'currency' => $this->currency,
            'amount' => ReportDataValidator::numericValue($this->amount, 'taxSubtotals.amount'),
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
     * @param array<int, string> $fields
     */
    private function requireFields(array $fields, string $context): void
    {
        foreach ($fields as $field) {
            if (is_null($this->{$field}) || $this->{$field} === '') {
                throw new InvalidArgumentException("{$context} requires {$field}.");
            }
        }
    }

    /** @param array<int, string> $allowedFields */
    private function requireOnlyFields(array $allowedFields, string $context): void
    {
        foreach (get_object_vars($this) as $field => $value) {
            if (! is_null($value) && ! in_array($field, $allowedFields, true)) {
                throw new InvalidArgumentException("{$context} does not support {$field}.");
            }
        }
    }
}
