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
final readonly class B2BIPaymentData implements Arrayable, JsonSerializable
{
    /**
     * @param array<int, TaxSubtotalData> $taxSubtotals
     */
    public function __construct(
        public string $invoiceNumber,
        public string $paymentDate,
        public int|float|string|null $amount = null,
        public ?string $currency = null,
        public ?string $issueDate = null,
        public ?string $paymentMeansCode = null,
        public array $taxSubtotals = [],
    ) {
        ReportDataValidator::assertNonEmptyString($this->invoiceNumber, 'b2biPayments.invoiceNumber');
        ReportDataValidator::assertDate($this->paymentDate, 'b2biPayments.paymentDate');

        if (! is_null($this->amount)) {
            ReportDataValidator::assertNumeric($this->amount, 'b2biPayments.amount');
        }

        if (! is_null($this->currency)) {
            ReportDataValidator::assertNonEmptyString($this->currency, 'b2biPayments.currency');
        }

        if (! is_null($this->issueDate)) {
            ReportDataValidator::assertDate($this->issueDate, 'b2biPayments.issueDate');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            invoiceNumber: ReportDataValidator::assertNonEmptyString($data['invoiceNumber'] ?? null, 'b2biPayments.invoiceNumber'),
            paymentDate: ReportDataValidator::assertDate($data['paymentDate'] ?? null, 'b2biPayments.paymentDate'),
            amount: array_key_exists('amount', $data) && ! is_null($data['amount'])
                ? ReportDataValidator::assertNumeric($data['amount'], 'b2biPayments.amount')
                : null,
            currency: ReportDataValidator::assertOptionalString($data['currency'] ?? null, 'b2biPayments.currency'),
            issueDate: array_key_exists('issueDate', $data) && ! is_null($data['issueDate'])
                ? ReportDataValidator::assertDate($data['issueDate'], 'b2biPayments.issueDate')
                : null,
            paymentMeansCode: ReportDataValidator::assertOptionalString($data['paymentMeansCode'] ?? null, 'b2biPayments.paymentMeansCode'),
            taxSubtotals: self::taxSubtotalsFromArray($data['taxSubtotals'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'invoiceNumber' => $this->invoiceNumber,
            'issueDate' => $this->issueDate,
            'paymentDate' => $this->paymentDate,
            'paymentMeansCode' => $this->paymentMeansCode,
            'amount' => is_null($this->amount) ? null : ReportDataValidator::numericValue($this->amount, 'b2biPayments.amount'),
            'currency' => $this->currency,
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
                ReportDataValidator::assertArray($taxSubtotal, 'b2biPayments.taxSubtotals.*'),
            ),
            ReportDataValidator::assertList($data, 'b2biPayments.taxSubtotals'),
        );
    }
}
