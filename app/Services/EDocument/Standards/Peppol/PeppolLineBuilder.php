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

namespace App\Services\EDocument\Standards\Peppol;

use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use InvoiceNinja\EInvoice\Models\Peppol\PriceType\Price;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PriceAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNoteLineType\CreditNoteLine;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\ClassifiedTaxCategory;
use App\Services\EDocument\Standards\Peppol;

class PeppolLineBuilder
{
    public function __construct(private Peppol $peppol) {}

    /**
    * getInvoiceLines
    *
    * Compiles the invoice line items of the document
    *
    * @return array
    */
    public function getInvoiceLines(): array
    {
        return $this->buildLines(false);
    }

    /**
     * getCreditNoteLines
     *
     * Compiles the credit note line items of the document
     *
     * @return array
     */
    public function getCreditNoteLines(): array
    {
        return $this->buildLines(true);
    }

    /**
     * Shared line builder for both invoice and credit note lines.
     *
     * Credit notes differ from invoices in:
     *  - Line type: CreditNoteLine vs InvoiceLine
     *  - Quantity type: CreditedQuantity vs InvoicedQuantity
     *  - Amounts wrapped in abs() to ensure positive values
     *
     * @param  bool $isCreditNote
     * @return array
     */
    private function buildLines(bool $isCreditNote): array
    {
        $lines = [];
        $invoice = $this->peppol->getInvoiceModel();
        $taxCalculator = $this->peppol->getTaxCalculator();
        $currencyCode = $invoice->client->currency()->code;

        $items = array_values(array_filter(
            (array) $invoice->line_items,
            fn($item) => !$this->isBlankItem($item)
        ));

        foreach ($items as $key => $item) {

            $_item = $this->buildItem($item, $taxCalculator);

            if ($isCreditNote) {
                $line = new CreditNoteLine();
            } else {
                $line = new InvoiceLine();
            }

            $id = new ID();
            $id->value = (string) ($key + 1);
            $line->ID = $id;

            // Quantity
            if ($isCreditNote) {
                $qty = new \InvoiceNinja\EInvoice\Models\Peppol\QuantityType\CreditedQuantity();
                $qty->amount = (string) $this->peppol->normalizeAmount($item->quantity);
                $qty->unitCode = $item->unit_code ?? 'C62';
                $line->CreditedQuantity = $qty;
            } else {
                $qty = new \InvoiceNinja\EInvoice\Models\Peppol\QuantityType\InvoicedQuantity();
                $qty->amount = $item->quantity;
                $qty->unitCode = $item->unit_code ?? 'C62';
                $line->InvoicedQuantity = $qty;
            }

            // Line Extension Amount
            $lineTotal = $invoice->uses_inclusive_taxes
                ? round($item->line_total - $this->peppol->calcInclusiveLineTax($item->tax_rate1, $item->line_total), 2)
                : round($item->line_total, 2);

            $lea = new LineExtensionAmount();
            $lea->currencyID = $currencyCode;
            $lea->amount = (string) $this->peppol->normalizeAmount($lineTotal);
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            // Price and Discounts
            $this->buildPriceAndDiscounts($line, $item, $invoice, $currencyCode, $isCreditNote);

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Builds the Item element with classified tax categories.
     *
     * @param  object $item
     * @param  PeppolTaxCalculator $taxCalculator
     * @return Item
     */
    private function buildItem(object $item, PeppolTaxCalculator $taxCalculator): Item
    {
        $_item = new Item();
        $_item->Name = strlen($item->product_key ?? '') >= 1 ? $item->product_key : ctrans('texts.item');
        $_item->Description = $item->notes ?? '';

        $ctc = new ClassifiedTaxCategory();
        $ctc->ID = new ID();
        $ctc->ID->value = $taxCalculator->getTaxType($item->tax_id);

        if ($item->tax_rate1 > 0) {
            $ctc->Percent = (string) $item->tax_rate1;
        }

        $ts = new TaxScheme();
        $id = new ID();
        $id->value = $taxCalculator->standardizeTaxSchemeId($item->tax_name1);
        $ts->ID = $id;
        $ctc->TaxScheme = $ts;

        if (floatval($item->tax_rate1) === 0.0) {
            $ctc = $taxCalculator->resolveTaxExemptReason($item, $ctc);

            if ($this->peppol->getTaxCategoryId() == 'O') {
                unset($ctc->Percent);
            }
        }

        $_item->ClassifiedTaxCategory[] = $ctc;

        if ($item->tax_rate2 > 0) {
            $_item->ClassifiedTaxCategory[] = $this->buildTaxCategory($taxCalculator, $item->tax_id, $item->tax_rate2, $item->tax_name2);
        }

        if ($item->tax_rate3 > 0) {
            $_item->ClassifiedTaxCategory[] = $this->buildTaxCategory($taxCalculator, $item->tax_id, $item->tax_rate3, $item->tax_name3);
        }

        return $_item;
    }

    /**
     * Builds a ClassifiedTaxCategory for secondary/tertiary tax rates.
     *
     * @param  PeppolTaxCalculator $taxCalculator
     * @param  string $taxId
     * @param  float $taxRate
     * @param  string $taxName
     * @return ClassifiedTaxCategory
     */
    private function buildTaxCategory(PeppolTaxCalculator $taxCalculator, string $taxId, float $taxRate, string $taxName): ClassifiedTaxCategory
    {
        $ctc = new ClassifiedTaxCategory();
        $ctc->ID = new ID();
        $ctc->ID->value = $taxCalculator->getTaxType($taxId);
        $ctc->Percent = (string) $taxRate;

        $ts = new TaxScheme();
        $id = new ID();
        $id->value = $taxCalculator->standardizeTaxSchemeId($taxName);
        $ts->ID = $id;
        $ctc->TaxScheme = $ts;

        return $ctc;
    }

    /**
     * Builds the Price element and optional AllowanceCharge for line discounts.
     *
     * @param  InvoiceLine|CreditNoteLine $line
     * @param  object $item
     * @param  object $invoice
     * @param  string $currencyCode
     * @param  bool $isCreditNote
     * @return void
     */
    private function buildPriceAndDiscounts(InvoiceLine|CreditNoteLine $line, object $item, object $invoice, string $currencyCode, bool $isCreditNote): void
    {
        $cost = $isCreditNote ? abs($item->cost) : $item->cost;

        if ($item->discount > 0) {

            $basePrice = new Price();
            $basePriceAmount = new PriceAmount();
            $basePriceAmount->currencyID = $currencyCode;
            $basePriceAmount->amount = (string) $cost;
            $basePrice->PriceAmount = $basePriceAmount;

            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->ChargeIndicator = 'false';
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $currencyCode;
            $allowanceCharge->Amount->amount = number_format($this->calculateTotalItemDiscountAmount($item), 2, '.', '');
            $this->peppol->addToAllowanceTotal($this->calculateTotalItemDiscountAmount($item));

            if ($item->discount > 0 && !$item->is_amount_discount) {

                $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                $allowanceCharge->BaseAmount->currencyID = $currencyCode;
                $allowanceCharge->BaseAmount->amount = (string) round($isCreditNote ? abs($item->cost * $item->quantity) : ($item->cost * $item->quantity), 2);

                $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                $mfn->value = (string) round($item->discount, 2);
                $allowanceCharge->MultiplierFactorNumeric = $mfn;
            }

            $allowanceCharge->AllowanceChargeReason = ctrans('texts.discount');

            $line->Price = $basePrice;
            $line->AllowanceCharge[] = $allowanceCharge;

        } else {
            $price = new Price();
            $pa = new PriceAmount();
            $pa->currencyID = $currencyCode;
            // Credit notes use abs(cost); invoices use net_cost for inclusive taxes
            $pa->amount = $isCreditNote
                ? (string) abs($item->cost)
                : ($invoice->uses_inclusive_taxes ? (string) $item->net_cost : (string) $item->cost);
            $price->PriceAmount = $pa;
            $line->Price = $price;
        }
    }

    public function calculateTotalItemDiscountAmount($item): float
    {

        if ($item->is_amount_discount) {
            return $item->discount;
        }

        return ($item->cost * $item->quantity) * ($item->discount / 100);

    }

    /**
     * A line item is "blank" — and dropped from the Peppol document — when it
     * has neither a billable amount nor a usable tax-name signal. Such rows
     * cannot produce a valid Peppol line and contribute nothing to totals,
     * so dropping them is loss-less.
     *
     * Why both conditions: missing tax_name1 means the line gets a
     * synthesised category code on the line (PeppolTaxCalculator::getTaxType)
     * but no matching VAT breakdown entry (PeppolTaxCalculator::getAllUsedTaxes
     * filters by `strlen(tax_name1) > 1`), so BR-S-01 / BR-Z-01 / BR-E-01 /
     * BR-AE-01 / BR-K-01 / BR-G-01 / BR-O-01 fail. Combined with cost==0,
     * dropping the row is loss-less.
     *
     * A row with non-zero cost is never dropped, even if tax_name1 is empty —
     * that row is a real Peppol-validity failure and the schematron will
     * surface it; silently dropping would change the invoice total.
     *
     * Half-cent epsilon for the cost comparison matches the rounding regime
     * used elsewhere in the builder (round($..., 2)).
     */
    private function isBlankItem(object $item): bool
    {
        return abs((float) ($item->cost ?? 0)) < 0.005
            && strlen((string) ($item->tax_name1 ?? '')) <= 1;
    }
}
