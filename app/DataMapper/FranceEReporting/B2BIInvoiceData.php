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
use App\Utils\BcMath;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class B2BIInvoiceData implements Arrayable, JsonSerializable
{
    /**
     * @param array<int, TaxSubtotalData> $taxSubtotals
     * @param array<int, array<string, mixed>> $invoiceLines
     */
    public function __construct(
        public string $invoiceNumber,
        public string $issueDate,
        public string $documentCurrency,
        public int|float|string $amountIncludingVat,
        public ?string $dueDate = null,
        public ?DeclarantPartyData $accountingSupplierParty = null,
        public ?DeclarantPartyData $accountingCustomerParty = null,
        public array $taxSubtotals = [],
        public array $invoiceLines = [],
    ) {
        ReportDataValidator::assertNonEmptyString($this->invoiceNumber, 'b2biInvoices.invoiceNumber');
        if (mb_strlen($this->invoiceNumber) > 35) {
            throw new \InvalidArgumentException('b2biInvoices.invoiceNumber must not exceed 35 characters.');
        }

        ReportDataValidator::assertDate($this->issueDate, 'b2biInvoices.issueDate');
        ReportDataValidator::assertNonEmptyString($this->documentCurrency, 'b2biInvoices.documentCurrency');
        if ($this->documentCurrency !== 'EUR') {
            throw new \InvalidArgumentException('Only EUR B2Bi transaction reports are currently supported.');
        }

        ReportDataValidator::assertCurrencyAmount($this->amountIncludingVat, 'b2biInvoices.amountIncludingVat');

        if (! is_null($this->dueDate)) {
            ReportDataValidator::assertDate($this->dueDate, 'b2biInvoices.dueDate');
        }

        if ($this->taxSubtotals === []) {
            throw new \InvalidArgumentException('b2biInvoices.taxSubtotals requires at least one item.');
        }

        if ($this->invoiceLines === []) {
            throw new \InvalidArgumentException('b2biInvoices.invoiceLines requires at least one item.');
        }

        if (is_null($this->accountingSupplierParty) || is_null($this->accountingCustomerParty)) {
            throw new \InvalidArgumentException('b2biInvoices requires accountingSupplierParty and accountingCustomerParty.');
        }

        $this->accountingSupplierParty->toB2BIAccountingPartyArray();
        $this->accountingCustomerParty->toB2BIAccountingPartyArray();

        $gross = '0';

        foreach ($this->taxSubtotals as $subtotal) {
            $subtotal->toB2BITransactionArray();
            $gross = BcMath::add($gross, BcMath::add($subtotal->taxableAmount, $subtotal->taxAmount, 4), 4);
        }

        if (BcMath::greaterThan(BcMath::abs(BcMath::sub($gross, $this->amountIncludingVat, 4), 4), '0.01', 4)) {
            throw new \InvalidArgumentException('b2biInvoices.amountIncludingVat must reconcile with taxSubtotals within 0.01 EUR.');
        }

        $lineNet = '0';
        $subtotalNet = '0';

        foreach ($this->invoiceLines as $line) {
            $projected = self::storecoveInvoiceLine($line);
            $lineNet = BcMath::add($lineNet, $projected['amountExcludingVat'], 4);
        }

        foreach ($this->taxSubtotals as $subtotal) {
            $subtotalNet = BcMath::add($subtotalNet, $subtotal->taxableAmount, 4);
        }

        if (BcMath::greaterThan(BcMath::abs(BcMath::sub($lineNet, $subtotalNet, 4), 4), '0.01', 4)) {
            throw new \InvalidArgumentException('b2biInvoices invoiceLines must reconcile with taxable taxSubtotals within 0.01 EUR.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            invoiceNumber: ReportDataValidator::assertNonEmptyString($data['invoiceNumber'] ?? null, 'b2biInvoices.invoiceNumber'),
            issueDate: ReportDataValidator::assertDate($data['issueDate'] ?? null, 'b2biInvoices.issueDate'),
            documentCurrency: ReportDataValidator::assertNonEmptyString($data['documentCurrency'] ?? null, 'b2biInvoices.documentCurrency'),
            amountIncludingVat: ReportDataValidator::assertCurrencyAmount($data['amountIncludingVat'] ?? null, 'b2biInvoices.amountIncludingVat'),
            dueDate: array_key_exists('dueDate', $data) && ! is_null($data['dueDate'])
                ? ReportDataValidator::assertDate($data['dueDate'], 'b2biInvoices.dueDate')
                : null,
            accountingSupplierParty: array_key_exists('accountingSupplierParty', $data) && ! is_null($data['accountingSupplierParty'])
                ? DeclarantPartyData::fromArray(ReportDataValidator::assertArray($data['accountingSupplierParty'], 'b2biInvoices.accountingSupplierParty'))
                : null,
            accountingCustomerParty: array_key_exists('accountingCustomerParty', $data) && ! is_null($data['accountingCustomerParty'])
                ? DeclarantPartyData::fromArray(ReportDataValidator::assertArray($data['accountingCustomerParty'], 'b2biInvoices.accountingCustomerParty'))
                : null,
            taxSubtotals: self::taxSubtotalsFromArray($data['taxSubtotals'] ?? []),
            invoiceLines: self::invoiceLinesFromArray($data['invoiceLines'] ?? []),
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
            'dueDate' => $this->dueDate,
            'documentCurrency' => $this->documentCurrency,
            'amountIncludingVat' => ReportDataValidator::numericValue($this->amountIncludingVat, 'b2biInvoices.amountIncludingVat'),
            'accountingSupplierParty' => $this->accountingSupplierParty->toB2BIAccountingPartyArray(),
            'accountingCustomerParty' => $this->accountingCustomerParty->toB2BIAccountingPartyArray(),
            'taxSubtotals' => array_values(array_map(
                static fn (TaxSubtotalData $taxSubtotal): array => $taxSubtotal->toB2BITransactionArray(),
                $this->taxSubtotals,
            )),
            'invoiceLines' => array_values(array_map([self::class, 'storecoveInvoiceLine'], $this->invoiceLines)),
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== []);
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private static function storecoveInvoiceLine(array $line): array
    {
        $unknownKeys = array_diff(array_keys($line), ['description', 'amountExcludingVat', 'tax']);

        if ($unknownKeys !== []) {
            throw new \InvalidArgumentException('B2Bi invoice line contains unsupported fields: '.implode(', ', $unknownKeys).'.');
        }

        $tax = ReportDataValidator::assertArray($line['tax'] ?? null, 'b2biInvoices.invoiceLines.tax');
        $unknownTaxKeys = array_diff(array_keys($tax), ['percentage', 'category', 'country']);

        if ($unknownTaxKeys !== []) {
            throw new \InvalidArgumentException('B2Bi invoice line tax contains unsupported fields: '.implode(', ', $unknownTaxKeys).'.');
        }

        $category = ReportDataValidator::assertNonEmptyString($tax['category'] ?? null, 'b2biInvoices.invoiceLines.tax.category');

        if (! FranceEReportTaxCategory::isSupported($category)) {
            throw new \InvalidArgumentException('b2biInvoices.invoiceLines.tax.category is not supported for France e-reporting.');
        }

        return [
            'description' => ReportDataValidator::assertNonEmptyString($line['description'] ?? null, 'b2biInvoices.invoiceLines.description'),
            'amountExcludingVat' => ReportDataValidator::numericValue(
                ReportDataValidator::assertCurrencyAmount($line['amountExcludingVat'] ?? null, 'b2biInvoices.invoiceLines.amountExcludingVat'),
                'b2biInvoices.invoiceLines.amountExcludingVat',
            ),
            'tax' => [
                'percentage' => ReportDataValidator::numericValue(
                    ReportDataValidator::assertPercentage($tax['percentage'] ?? null, 'b2biInvoices.invoiceLines.tax.percentage'),
                    'b2biInvoices.invoiceLines.tax.percentage',
                ),
                'category' => FranceEReportTaxCategory::normalize($category),
                'country' => ReportDataValidator::assertCountryCode($tax['country'] ?? null, 'b2biInvoices.invoiceLines.tax.country'),
            ],
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
     * @return array<int, TaxSubtotalData>
     */
    private static function taxSubtotalsFromArray(mixed $data): array
    {
        return array_map(
            static fn (mixed $taxSubtotal): TaxSubtotalData => TaxSubtotalData::fromArray(
                ReportDataValidator::assertArray($taxSubtotal, 'b2biInvoices.taxSubtotals.*'),
            ),
            ReportDataValidator::assertList($data, 'b2biInvoices.taxSubtotals'),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function invoiceLinesFromArray(mixed $data): array
    {
        return array_map(
            static function (mixed $line): array {
                $line = ReportDataValidator::assertArray($line, 'b2biInvoices.invoiceLines.*');

                ReportDataValidator::assertNonEmptyString($line['description'] ?? null, 'b2biInvoices.invoiceLines.description');
                ReportDataValidator::assertCurrencyAmount($line['amountExcludingVat'] ?? null, 'b2biInvoices.invoiceLines.amountExcludingVat');

                if (array_key_exists('tax', $line) && ! is_null($line['tax'])) {
                    $tax = ReportDataValidator::assertArray($line['tax'], 'b2biInvoices.invoiceLines.tax');
                    ReportDataValidator::assertNumeric($tax['percentage'] ?? null, 'b2biInvoices.invoiceLines.tax.percentage');
                    ReportDataValidator::assertOptionalString($tax['category'] ?? null, 'b2biInvoices.invoiceLines.tax.category');
                    ReportDataValidator::assertOptionalString($tax['country'] ?? null, 'b2biInvoices.invoiceLines.tax.country');
                }

                return $line;
            },
            ReportDataValidator::assertList($data, 'b2biInvoices.invoiceLines'),
        );
    }
}
