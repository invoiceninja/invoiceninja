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
use App\Services\EDocument\Standards\France\FranceEReportTaxCategory;
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
        ReportDataValidator::assertDate($this->issueDate, 'b2biInvoices.issueDate');
        ReportDataValidator::assertNonEmptyString($this->documentCurrency, 'b2biInvoices.documentCurrency');
        ReportDataValidator::assertNumeric($this->amountIncludingVat, 'b2biInvoices.amountIncludingVat');

        if (! is_null($this->dueDate)) {
            ReportDataValidator::assertDate($this->dueDate, 'b2biInvoices.dueDate');
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
            amountIncludingVat: ReportDataValidator::assertNumeric($data['amountIncludingVat'] ?? null, 'b2biInvoices.amountIncludingVat'),
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
            'accountingSupplierParty' => $this->accountingSupplierParty?->toArray(),
            'accountingCustomerParty' => $this->accountingCustomerParty?->toArray(),
            'taxSubtotals' => array_values(array_map(
                static fn (TaxSubtotalData $taxSubtotal): array => $taxSubtotal->toArray(),
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
        if (array_key_exists('amountExcludingVat', $line) && ! is_null($line['amountExcludingVat'])) {
            $line['amountExcludingVat'] = ReportDataValidator::numericValue($line['amountExcludingVat'], 'b2biInvoices.invoiceLines.amountExcludingVat');
        }

        if (array_key_exists('tax', $line) && is_array($line['tax'])) {
            if (array_key_exists('percentage', $line['tax']) && ! is_null($line['tax']['percentage'])) {
                $line['tax']['percentage'] = ReportDataValidator::numericValue($line['tax']['percentage'], 'b2biInvoices.invoiceLines.tax.percentage');
            }

            if (array_key_exists('category', $line['tax'])) {
                $line['tax']['category'] = FranceEReportTaxCategory::normalize($line['tax']['category']);
            }
        }

        return array_filter($line, static fn (mixed $value): bool => ! is_null($value) && $value !== []);
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
                ReportDataValidator::assertNumeric($line['amountExcludingVat'] ?? null, 'b2biInvoices.invoiceLines.amountExcludingVat');

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
