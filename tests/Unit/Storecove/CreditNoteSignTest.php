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

namespace Tests\Unit\Storecove;

use Tests\TestCase;
use App\Services\EDocument\Gateway\Storecove\Models\CreditLines;
use App\Services\EDocument\Gateway\Storecove\Models\InvoiceLines;

/**
 * Guards the sign convention on the Storecove credit line at the layer that
 * actually reaches Storecove: the PUBLIC PROPERTIES (the Symfony serializer
 * encodes raw property values — the getters are not consulted during
 * normalisation, so this test reads the properties directly).
 *
 * Storecove represents a credit as a NEGATIVE INVOICE:
 *   - quantity stays POSITIVE
 *   - every monetary/price field is NEGATIVE
 *   - the line stays arithmetically coherent: item_price × quantity == line total
 *
 * @see \App\Services\EDocument\Gateway\Storecove\Models\CreditLines::__construct
 */
class CreditNoteSignTest extends TestCase
{
    private function creditLine(?float $itemPrice, ?float $quantity, ?float $amountExcludingVat, ?float $amountExcludingTax, ?float $amountIncludingTax): CreditLines
    {
        return new CreditLines(
            line_id: '1',
            description: 'A widget',
            name: 'Widget',
            order_line_reference_line_id: null,
            invoice_period: null,
            item_price: $itemPrice,
            quantity: $quantity,
            base_quantity: null,
            quantity_unit_code: 'C62',
            allowance_charges: null,
            amount_excluding_vat: $amountExcludingVat,
            amount_excluding_tax: $amountExcludingTax,
            amount_including_tax: $amountIncludingTax,
            taxes_duties_fees: [],
            accounting_cost: null,
            references: null,
            additional_item_properties: null,
            sellers_item_identification: null,
            buyers_item_identification: null,
            standard_item_identification: null,
            standard_item_identification_scheme_id: null,
            standard_item_identification_scheme_agency_id: null,
            note: null,
        );
    }

    /**
     * A credit line built from the (positive) Peppol values must serialise as a
     * negative-invoice line: positive quantity, negative price and amounts.
     */
    public function testCreditLineIsPositiveQuantityNegativeAmounts(): void
    {
        // Positive inputs, as decoded from the positive Peppol CreditNote.
        $line = $this->creditLine(100.0, 2.0, 200.0, 50.0, 238.0);

        $this->assertSame(2.0, $line->quantity, 'CreditedQuantity must stay POSITIVE');
        $this->assertSame(-100.0, $line->item_price, 'Unit price must be NEGATIVE');
        $this->assertSame(-200.0, $line->amount_excluding_vat, 'LineExtensionAmount must be NEGATIVE');
        $this->assertSame(-50.0, $line->amount_excluding_tax, 'Price value must be NEGATIVE');
        $this->assertSame(-238.0, $line->amount_including_tax, 'Line tax-inclusive amount must be NEGATIVE');

        // Arithmetic coherence: unit price × quantity == line extension amount.
        $this->assertSame(
            $line->item_price * $line->quantity,
            $line->amount_excluding_vat,
            'item_price × quantity must reconcile with the line extension amount'
        );
    }

    /**
     * Null amount fields must remain null — not be coerced to 0 by the negation
     * (which would inject spurious zero amounts into the payload).
     */
    public function testNullAmountsStayNull(): void
    {
        $line = $this->creditLine(null, 1.0, null, null, null);

        $this->assertNull($line->item_price);
        $this->assertNull($line->amount_excluding_vat);
        $this->assertNull($line->amount_excluding_tax);
        $this->assertNull($line->amount_including_tax);
    }

    /**
     * Control: the non-credit InvoiceLines path negates nothing — a positive
     * invoice line stays fully positive.
     */
    public function testInvoiceLineControlStaysPositive(): void
    {
        $line = new InvoiceLines(
            line_id: '1',
            description: 'A widget',
            name: 'Widget',
            order_line_reference_line_id: null,
            invoice_period: null,
            item_price: 100.0,
            quantity: 2.0,
            base_quantity: null,
            quantity_unit_code: 'C62',
            allowance_charges: null,
            amount_excluding_vat: 200.0,
            amount_excluding_tax: 50.0,
            amount_including_tax: 238.0,
            taxes_duties_fees: [],
            accounting_cost: null,
            references: null,
            additional_item_properties: null,
            sellers_item_identification: null,
            buyers_item_identification: null,
            standard_item_identification: null,
            standard_item_identification_scheme_id: null,
            standard_item_identification_scheme_agency_id: null,
            note: null,
        );

        $this->assertSame(2.0, $line->quantity);
        $this->assertSame(100.0, $line->item_price);
        $this->assertSame(200.0, $line->amount_excluding_vat);
    }
}
