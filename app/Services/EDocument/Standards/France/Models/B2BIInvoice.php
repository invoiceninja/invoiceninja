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
use App\DataMapper\FranceEReporting\ReportDataValidator;

use Symfony\Component\Serializer\Attribute\SerializedPath;

class B2BIInvoice
{
    #[SerializedPath('[cbc:ID][#]')]
    public ?string $invoice_number = null;

    #[SerializedPath('[cbc:IssueDate]')]
    public ?string $issue_date = null;

    #[SerializedPath('[cbc:DueDate]')]
    public ?string $due_date = null;

    #[SerializedPath('[cbc:DocumentCurrencyCode]')]
    public ?string $document_currency_code = null;

    #[SerializedPath('[cac:LegalMonetaryTotal][cbc:TaxInclusiveAmount][#]')]
    public int|float|string|null $amount_including_vat = null;

    #[SerializedPath('[cac:AccountingSupplierParty][cac:Party]')]
    public ?B2BIParty $accounting_supplier_party = null;

    #[SerializedPath('[AccountingSupplierParty][Party]')]
    public ?B2BIParty $accounting_supplier_party_direct = null;

    #[SerializedPath('[cac:AccountingCustomerParty][cac:Party]')]
    public ?B2BIParty $accounting_customer_party = null;

    #[SerializedPath('[AccountingCustomerParty][Party]')]
    public ?B2BIParty $accounting_customer_party_direct = null;

    /**
     * @var array<int, B2BIInvoiceLine>|null
     */
    #[SerializedPath('[cac:InvoiceLine]')]
    public ?array $invoice_lines = null;

    /**
     * @var array<int, B2BIInvoiceLine>|null
     */
    #[SerializedPath('[InvoiceLine]')]
    public ?array $invoice_lines_direct = null;

    /**
     * @var array<int, B2BIInvoiceLine>|null
     */
    #[SerializedPath('[cac:CreditNoteLine]')]
    public ?array $credit_note_lines = null;

    /**
     * @var array<int, B2BIInvoiceLine>|null
     */
    #[SerializedPath('[CreditNoteLine]')]
    public ?array $credit_note_lines_direct = null;

    /**
     * @var array<int, B2BITaxSubtotal>|null
     */
    #[SerializedPath('[cac:TaxTotal][0][cac:TaxSubtotal]')]
    public ?array $tax_subtotals = null;

    #[SerializedPath('[cac:TaxTotal]')]
    public ?array $tax_total = null;

    #[SerializedPath('[TaxTotal]')]
    public ?array $tax_total_direct = null;

    public function __construct(
        ?string $invoice_number = null,
        ?string $issue_date = null,
        ?string $due_date = null,
        ?string $document_currency_code = null,
        int|float|string|null $amount_including_vat = null,
        ?B2BIParty $accounting_supplier_party = null,
        ?B2BIParty $accounting_supplier_party_direct = null,
        ?B2BIParty $accounting_customer_party = null,
        ?B2BIParty $accounting_customer_party_direct = null,
        ?array $invoice_lines = null,
        ?array $invoice_lines_direct = null,
        ?array $credit_note_lines = null,
        ?array $credit_note_lines_direct = null,
        ?array $tax_subtotals = null,
        ?array $tax_total = null,
        ?array $tax_total_direct = null,
    ) {
        $this->invoice_number = $invoice_number;
        $this->issue_date = $issue_date;
        $this->due_date = $due_date;
        $this->document_currency_code = $document_currency_code;
        $this->amount_including_vat = $amount_including_vat;
        $this->accounting_supplier_party = $accounting_supplier_party;
        $this->accounting_supplier_party_direct = $accounting_supplier_party_direct;
        $this->accounting_customer_party = $accounting_customer_party;
        $this->accounting_customer_party_direct = $accounting_customer_party_direct;
        $this->invoice_lines = $invoice_lines;
        $this->invoice_lines_direct = $invoice_lines_direct;
        $this->credit_note_lines = $credit_note_lines;
        $this->credit_note_lines_direct = $credit_note_lines_direct;
        $this->tax_subtotals = $tax_subtotals;
        $this->tax_total = $tax_total;
        $this->tax_total_direct = $tax_total_direct;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $supplier = $this->accounting_supplier_party ?? $this->accounting_supplier_party_direct;
        $country = $supplier?->country;

        return array_filter([
            'invoiceNumber' => $this->invoice_number,
            'issueDate' => $this->issue_date,
            'dueDate' => $this->due_date,
            'documentCurrency' => $this->document_currency_code,
            'amountIncludingVat' => is_null($this->amount_including_vat) ? null : ReportDataValidator::numericValue($this->amount_including_vat, 'b2biInvoices.amountIncludingVat'),
            'taxSubtotals' => $this->taxSubtotalsToArray($this->tax_subtotals
                ?? data_get($this->tax_total, '0.cac:TaxSubtotal')
                ?? data_get($this->tax_total_direct, '0.TaxSubtotal')
                ?? [], $country),
            'accountingSupplierParty' => $supplier?->toArray(),
            'accountingCustomerParty' => ($this->accounting_customer_party ?? $this->accounting_customer_party_direct)?->toArray(),
            'invoiceLines' => $this->invoiceLinesToArray(
                $this->invoice_lines
                    ?? $this->invoice_lines_direct
                    ?? $this->credit_note_lines
                    ?? $this->credit_note_lines_direct
                    ?? [],
                $country,
            ),
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== []);
    }

    /**
     * @param array<int, B2BIInvoiceLine|mixed> $invoiceLines
     * @return array<int, array<string, mixed>>
     */
    private function invoiceLinesToArray(array $invoiceLines, ?string $country): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $line): array => $line instanceof B2BIInvoiceLine ? $line->toArray($country) : (array) $line,
            $invoiceLines,
        )));
    }

    /**
     * @param array<int, B2BITaxSubtotal|mixed> $taxSubtotals
     * @return array<int, array<string, mixed>>
     */
    private function taxSubtotalsToArray(array $taxSubtotals, ?string $country): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $taxSubtotal): array => $taxSubtotal instanceof B2BITaxSubtotal ? $taxSubtotal->toArray($country) : (array) $taxSubtotal,
            $taxSubtotals,
        )));
    }
}
