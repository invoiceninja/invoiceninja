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
    public function __construct(private Peppol $peppol)
    {
    }

    /**
    * getInvoiceLines
    *
    * Compiles the invoice line items of the document
    *
    * @return array
    */
    public function getInvoiceLines(): array
    {
        $lines = [];
        $invoice = $this->peppol->getInvoiceModel();
        $taxCalculator = $this->peppol->getTaxCalculator();

        foreach ($invoice->line_items as $key => $item) {

            $_item = new Item();
            $_item->Name = strlen($item->product_key ?? '') >= 1 ? $item->product_key : ctrans('texts.item');
            $_item->Description = $item->notes;


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
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $taxCalculator->getTaxType($item->tax_id);
                $ctc->Percent = (string) $item->tax_rate2;

                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $taxCalculator->standardizeTaxSchemeId($item->tax_name2);
                $ts->ID = $id;
                $ctc->TaxScheme = $ts;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            if ($item->tax_rate3 > 0) {
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $taxCalculator->getTaxType($item->tax_id);
                $ctc->Percent = (string) $item->tax_rate3;

                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $taxCalculator->standardizeTaxSchemeId($item->tax_name3);
                $ts->ID = $id;
                $ctc->TaxScheme = $ts;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            $line = new InvoiceLine();

            $id = new ID();
            $id->value = (string) ($key + 1);
            $line->ID = $id;

            $iq = new \InvoiceNinja\EInvoice\Models\Peppol\QuantityType\InvoicedQuantity();
            $iq->amount = $item->quantity;
            $iq->unitCode = $item->unit_code ?? 'C62';
            $line->InvoicedQuantity = $iq;

            $line_extension_amount = $invoice->uses_inclusive_taxes ? round($item->line_total - $this->peppol->calcInclusiveLineTax($item->tax_rate1, $item->line_total), 2) : round($item->line_total, 2);

            $lea = new LineExtensionAmount();
            $lea->currencyID = $invoice->client->currency()->code;
            $lea->amount = (string) $line_extension_amount;
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            /** Builds the tax map for the document */
            // $this->getItemTaxes($item);

            // Handle Price and Discounts
            if ($item->discount > 0) {

                // Base Price (before discount)
                $basePrice = new Price();
                $basePriceAmount = new PriceAmount();
                $basePriceAmount->currencyID = $invoice->client->currency()->code;
                $basePriceAmount->amount = (string) $item->cost;
                $basePrice->PriceAmount = $basePriceAmount;

                // Add Allowance Charge to Price
                $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
                $allowanceCharge->ChargeIndicator = 'false'; // false = discount
                $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
                $allowanceCharge->Amount->currencyID = $invoice->client->currency()->code;
                $allowanceCharge->Amount->amount = number_format($this->calculateTotalItemDiscountAmount($item), 2, '.', '');
                $this->peppol->addToAllowanceTotal($this->calculateTotalItemDiscountAmount($item));


                // Add percentage if available
                if ($item->discount > 0 && !$item->is_amount_discount) {

                    $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                    $allowanceCharge->BaseAmount->currencyID = $invoice->client->currency()->code;
                    $allowanceCharge->BaseAmount->amount = (string) round(($item->cost * $item->quantity), 2);

                    $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                    $mfn->value = (string) round($item->discount, 2);
                    $allowanceCharge->MultiplierFactorNumeric = $mfn; // Convert percentage to decimal
                }

                // }
                // Required reason
                $allowanceCharge->AllowanceChargeReason = ctrans('texts.discount');

                $line->Price = $basePrice;
                $line->AllowanceCharge[] = $allowanceCharge;

            } else {
                // No discount case
                $price = new Price();
                $pa = new PriceAmount();
                $pa->currencyID = $invoice->client->currency()->code;
                $pa->amount = $invoice->uses_inclusive_taxes ? (string) $item->net_cost :(string) $item->cost;
                $price->PriceAmount = $pa;
                $line->Price = $price;
            }

            $lines[] = $line;
        }

        return $lines;
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
        $lines = [];
        $invoice = $this->peppol->getInvoiceModel();
        $taxCalculator = $this->peppol->getTaxCalculator();

        foreach ($invoice->line_items as $key => $item) {

            $_item = new Item();
            $_item->Name = strlen($item->product_key ?? '') >= 1 ? $item->product_key : ctrans('texts.item');
            $_item->Description = $item->notes;

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
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $taxCalculator->getTaxType($item->tax_id);
                $ctc->Percent = (string) $item->tax_rate2;

                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $taxCalculator->standardizeTaxSchemeId($item->tax_name2);
                $ts->ID = $id;
                $ctc->TaxScheme = $ts;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            if ($item->tax_rate3 > 0) {
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $taxCalculator->getTaxType($item->tax_id);
                $ctc->Percent = (string) $item->tax_rate3;

                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $taxCalculator->standardizeTaxSchemeId($item->tax_name3);
                $ts->ID = $id;
                $ctc->TaxScheme = $ts;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            $line = new CreditNoteLine();

            $id = new ID();
            $id->value = (string) ($key + 1);
            $line->ID = $id;

            // Use CreditedQuantity instead of InvoicedQuantity
            $cq = new \InvoiceNinja\EInvoice\Models\Peppol\QuantityType\CreditedQuantity();
            $cq->amount = (string) $this->peppol->isCreditNoteDocument() ? abs($item->quantity) : $item->quantity; // Ensure positive quantity
            $cq->unitCode = $item->unit_code ?? 'C62';
            $line->CreditedQuantity = $cq;

            $lea = new LineExtensionAmount();
            $lea->currencyID = $invoice->client->currency()->code;
            $lineTotal = $invoice->uses_inclusive_taxes
                ? round($item->line_total - $this->peppol->calcInclusiveLineTax($item->tax_rate1, $item->line_total), 2)
                : round($item->line_total, 2);
            $lea->amount = (string) abs($lineTotal); // Ensure positive amount
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            // Handle Price and Discounts
            if ($item->discount > 0) {

                // Base Price (before discount)
                $basePrice = new Price();
                $basePriceAmount = new PriceAmount();
                $basePriceAmount->currencyID = $invoice->client->currency()->code;
                $basePriceAmount->amount = (string) abs($item->cost);
                $basePrice->PriceAmount = $basePriceAmount;

                // Add Allowance Charge to Price
                $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
                $allowanceCharge->ChargeIndicator = 'false'; // false = discount
                $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
                $allowanceCharge->Amount->currencyID = $invoice->client->currency()->code;
                $allowanceCharge->Amount->amount = number_format($this->calculateTotalItemDiscountAmount($item), 2, '.', '');
                $this->peppol->addToAllowanceTotal($this->calculateTotalItemDiscountAmount($item));

                // Add percentage if available
                if ($item->discount > 0 && !$item->is_amount_discount) {

                    $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                    $allowanceCharge->BaseAmount->currencyID = $invoice->client->currency()->code;
                    $allowanceCharge->BaseAmount->amount = (string) round(abs($item->cost * $item->quantity), 2);

                    $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                    $mfn->value = (string) round($item->discount, 2);
                    $allowanceCharge->MultiplierFactorNumeric = $mfn;
                }

                $allowanceCharge->AllowanceChargeReason = ctrans('texts.discount');

                $line->Price = $basePrice;
                $line->AllowanceCharge[] = $allowanceCharge;

            } else {
                // No discount case
                $price = new Price();
                $pa = new PriceAmount();
                $pa->currencyID = $invoice->client->currency()->code;
                $pa->amount = (string) abs($item->cost);
                $price->PriceAmount = $pa;
                $line->Price = $price;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    public function calculateTotalItemDiscountAmount($item): float
    {

        if ($item->is_amount_discount) {
            return $item->discount;
        }

        return ($item->cost * $item->quantity) * ($item->discount / 100);

    }
}
