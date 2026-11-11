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
        if (mb_strlen($this->invoiceNumber) > 35) {
            throw new \InvalidArgumentException('b2biPayments.invoiceNumber must not exceed 35 characters.');
        }

        ReportDataValidator::assertDate($this->paymentDate, 'b2biPayments.paymentDate');

        if (! is_null($this->amount) || ! is_null($this->currency)) {
            throw new \InvalidArgumentException('B2Bi payment amount/currency mapping is disabled until Storecove-generated F10 XML proves it.');
        }

        if ($this->taxSubtotals === []) {
            throw new \InvalidArgumentException('b2biPayments.taxSubtotals requires at least one item.');
        }

        if (! is_null($this->issueDate)) {
            ReportDataValidator::assertDate($this->issueDate, 'b2biPayments.issueDate');
        }

        if (is_null($this->issueDate)) {
            throw new \InvalidArgumentException('b2biPayments.issueDate is required.');
        }

        foreach ($this->taxSubtotals as $subtotal) {
            $subtotal->toB2BIPaymentArray();

            if ($subtotal->currency !== 'EUR') {
                throw new \InvalidArgumentException('Only EUR B2Bi payment reports are currently supported.');
            }
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
            'taxSubtotals' => array_values(array_map(
                static fn (TaxSubtotalData $taxSubtotal): array => $taxSubtotal->toB2BIPaymentArray(),
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
                ReportDataValidator::assertArray($taxSubtotal, 'b2biPayments.taxSubtotals.*'),
            ),
            ReportDataValidator::assertList($data, 'b2biPayments.taxSubtotals'),
        );
    }
}
