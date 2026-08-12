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

use App\Utils\BcMath;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class B2CTransactionData implements Arrayable, JsonSerializable
{
    private const CATEGORIES = ['TLB1', 'TPS1', 'TNT1', 'TMA1'];

    private const VAT_PAYMENT_OPTIONS = ['customer', 'supplier'];

    /**
     * @param array<int, TaxSubtotalData> $taxSubtotals
     */
    public function __construct(
        public string $date,
        public string $category,
        public string $currency,
        public int|float|string $amountExcludingVat,
        public int|float|string $amountIncludingVat,
        public ?int $transactionsCount = null,
        public ?string $vatPaymentOption = null,
        public array $taxSubtotals = [],
    ) {
        ReportDataValidator::assertDate($this->date, 'b2cTransactions.date');
        ReportDataValidator::assertNonEmptyString($this->category, 'b2cTransactions.category');
        if (! in_array($this->category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('b2cTransactions.category is not supported for France e-reporting.');
        }

        ReportDataValidator::assertNonEmptyString($this->currency, 'b2cTransactions.currency');
        if ($this->currency !== 'EUR') {
            throw new \InvalidArgumentException('Only EUR B2C transaction reports are currently supported.');
        }

        ReportDataValidator::assertCurrencyAmount($this->amountExcludingVat, 'b2cTransactions.amountExcludingVat');
        ReportDataValidator::assertCurrencyAmount($this->amountIncludingVat, 'b2cTransactions.amountIncludingVat');
        if (! is_null($this->transactionsCount)) {
            ReportDataValidator::assertNonNegativeInteger($this->transactionsCount, 'b2cTransactions.transactionsCount');
        }

        if (! is_null($this->vatPaymentOption)
            && ! in_array($this->vatPaymentOption, self::VAT_PAYMENT_OPTIONS, true)) {
            throw new \InvalidArgumentException('b2cTransactions.vatPaymentOption must be customer or supplier.');
        }

        if ($this->taxSubtotals === []) {
            throw new \InvalidArgumentException('b2cTransactions.taxSubtotals requires at least one item.');
        }

        $taxableTotal = '0';
        $taxTotal = '0';

        foreach ($this->taxSubtotals as $subtotal) {
            $subtotal->toB2CTransactionArray();
            $taxableTotal = BcMath::add($taxableTotal, $subtotal->taxableAmount, 4);
            $taxTotal = BcMath::add($taxTotal, $subtotal->taxAmount, 4);
        }

        if (BcMath::greaterThan(BcMath::abs(BcMath::sub($taxableTotal, $this->amountExcludingVat, 4), 4), '0.01', 4)
            || BcMath::greaterThan(BcMath::abs(BcMath::sub(BcMath::add($taxableTotal, $taxTotal, 4), $this->amountIncludingVat, 4), 4), '0.01', 4)) {
            throw new \InvalidArgumentException('b2cTransactions amounts must reconcile with taxSubtotals within 0.01 EUR.');
        }
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
            amountExcludingVat: ReportDataValidator::assertCurrencyAmount($data['amountExcludingVat'] ?? null, 'b2cTransactions.amountExcludingVat'),
            amountIncludingVat: ReportDataValidator::assertCurrencyAmount($data['amountIncludingVat'] ?? null, 'b2cTransactions.amountIncludingVat'),
            transactionsCount: array_key_exists('transactionsCount', $data) && ! is_null($data['transactionsCount'])
                ? ReportDataValidator::assertNonNegativeInteger($data['transactionsCount'], 'b2cTransactions.transactionsCount')
                : null,
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
                static fn (TaxSubtotalData $taxSubtotal): array => $taxSubtotal->toB2CTransactionArray(),
                $this->taxSubtotals,
            )),
        ], static fn (mixed $value): bool => ! is_null($value));
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
