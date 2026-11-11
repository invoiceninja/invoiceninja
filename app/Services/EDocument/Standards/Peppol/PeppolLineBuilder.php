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
     *  - Signs from Peppol::normalizeCreditNoteLine() (price unsigned, qty carries leftover sign)
     *
     * @param  bool $isCreditNote
     * @return array
     */
    private function buildLines(bool $isCreditNote): array
    {
        $lines = [];
        $this->peppol->setGlobalTaxCategories([]);
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

            $rawLineTotal = $invoice->uses_inclusive_taxes
                ? round($item->line_total - $this->peppol->calcInclusiveLineTax($item->tax_rate1, $item->line_total), 2)
                : round($item->line_total, 2);

            if ($isCreditNote) {
                $hasLineAllowance = (float) ($item->discount ?? 0) > 0;

                $signed = $this->peppol->normalizeCreditNoteLine(
                    (float) $item->quantity,
                    (float) $item->cost,
                    (float) $rawLineTotal,
                    $hasLineAllowance,
                );

                $qty = new \InvoiceNinja\EInvoice\Models\Peppol\QuantityType\CreditedQuantity();
                $qty->amount = (string) $signed['quantity'];
                $qty->unitCode = $item->unit_code ?? 'C62';
                $line->CreditedQuantity = $qty;

                $lea = new LineExtensionAmount();
                $lea->currencyID = $currencyCode;
                $lea->amount = (string) $signed['line_total'];
                $line->LineExtensionAmount = $lea;
                $line->Item = $_item;

                $this->buildPriceAndDiscounts($line, $item, $invoice, $currencyCode, true, $signed['price'], $signed['quantity']);
            } else {
                $qty = new \InvoiceNinja\EInvoice\Models\Peppol\QuantityType\InvoicedQuantity();
                $qty->amount = $item->quantity;
                $qty->unitCode = $item->unit_code ?? 'C62';
                $line->InvoicedQuantity = $qty;

                $lea = new LineExtensionAmount();
                $lea->currencyID = $currencyCode;
                $lea->amount = (string) $rawLineTotal;
                $line->LineExtensionAmount = $lea;
                $line->Item = $_item;

                $this->buildPriceAndDiscounts($line, $item, $invoice, $currencyCode, false);
            }

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

        $_item->ClassifiedTaxCategory[] = $this->buildTaxCategory(
            $taxCalculator, $item, (float) $item->tax_rate1, (string) $item->tax_name1,
        );

        if ($item->tax_rate2 > 0) {
            $_item->ClassifiedTaxCategory[] = $this->buildTaxCategory($taxCalculator, $item, $item->tax_rate2, $item->tax_name2);
        }

        if ($item->tax_rate3 > 0) {
            $_item->ClassifiedTaxCategory[] = $this->buildTaxCategory($taxCalculator, $item, $item->tax_rate3, $item->tax_name3);
        }

        return $_item;
    }

    /**
     * Builds the line representation of a resolved tax category.
     *
     * @param  PeppolTaxCalculator $taxCalculator
     * @param  object $item
     * @param  float $taxRate
     * @param  string $taxName
     * @return ClassifiedTaxCategory
     */
    private function buildTaxCategory(PeppolTaxCalculator $taxCalculator, object $item, float $taxRate, string $taxName): ClassifiedTaxCategory
    {
        $resolved = $taxCalculator->resolveLineTaxCategory($item, $taxRate, $taxName);
        $category = new ClassifiedTaxCategory();
        $category->ID = clone $resolved->ID;
        if (isset($resolved->Percent)) {
            $category->Percent = $resolved->Percent;
        }
        $category->TaxScheme = clone $resolved->TaxScheme;

        return $category;
    }

    /**
     * Builds the Price element and optional AllowanceCharge for line discounts.
     *
     * @param  InvoiceLine|CreditNoteLine $line
     * @param  object $item
     * @param  object $invoice
     * @param  string $currencyCode
     * @param  bool $isCreditNote
     * @param  float|null $creditNoteUnitPrice Unsigned unit price from normalizeCreditNoteLine()
     * @param  float|null $normalizedCreditQuantity CreditedQuantity after normalizeCreditNoteLine()
     * @return void
     */
    private function buildPriceAndDiscounts(
        InvoiceLine|CreditNoteLine $line,
        object $item,
        object $invoice,
        string $currencyCode,
        bool $isCreditNote,
        ?float $creditNoteUnitPrice = null,
        ?float $normalizedCreditQuantity = null,
    ): void {
        $cost = $isCreditNote ? ($creditNoteUnitPrice ?? abs($item->cost)) : $item->cost;

        if ($item->discount > 0) {

            $basePrice = new Price();
            $basePriceAmount = new PriceAmount();
            $basePriceAmount->currencyID = $currencyCode;
            $basePriceAmount->amount = (string) $cost;
            $basePrice->PriceAmount = $basePriceAmount;

            $creditedQty = $isCreditNote
                ? (float) ($normalizedCreditQuantity ?? $item->quantity)
                : (float) $item->quantity;
            $discountAmount = $this->calculateTotalItemDiscountAmount($item);

            // Credit notes: charge iff the discount increases LineExtensionAmount
            // magnitude relative to qty × price (PEPPOL-EN16931-R120). Flat vs
            // percentage discounts diverge on signed rows; one delta rule covers both.
            if ($isCreditNote) {
                $lea = (float) $line->LineExtensionAmount->amount;
                $delta = $lea - ($creditedQty * $cost);
                $useLineCharge = $delta > 0.005;
                $discountAmount = abs($delta);
            } else {
                $useLineCharge = false;
            }

            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->ChargeIndicator = $useLineCharge ? 'true' : 'false';
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $currencyCode;
            $allowanceCharge->Amount->amount = number_format($discountAmount, 2, '.', '');

            if (!$useLineCharge) {
                $this->peppol->addToAllowanceTotal($discountAmount);
            }

            if ($item->discount > 0 && !$item->is_amount_discount) {

                $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                $allowanceCharge->BaseAmount->currencyID = $currencyCode;
                $allowanceCharge->BaseAmount->amount = (string) round(
                    $isCreditNote ? abs($creditedQty * $cost) : ($item->cost * $item->quantity),
                    2
                );

                $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                $mfn->value = (string) round($item->discount, 2);
                $allowanceCharge->MultiplierFactorNumeric = $mfn;
            }

            $allowanceCharge->AllowanceChargeReason = 'Discount';

// $allowanceCharge->AllowanceChargeReason = ctrans('texts.discount');

            $line->Price = $basePrice;
            $line->AllowanceCharge[] = $allowanceCharge;

        } else {
            $price = new Price();
            $pa = new PriceAmount();
            $pa->currencyID = $currencyCode;
            $pa->amount = $isCreditNote
                ? (string) $cost
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
     * A row with non-zero cost is never dropped, even if tax_name1 is empty.
     * Its category is resolved from the product and transaction context, and
     * its amount must remain in the VAT breakdown and document totals.
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
