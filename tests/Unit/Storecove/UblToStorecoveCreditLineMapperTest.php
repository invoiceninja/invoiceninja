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
use App\Services\EDocument\Gateway\Storecove\Models\AllowanceCharges;
use App\Services\EDocument\Gateway\Storecove\UblToStorecoveCreditLineMapper;

class UblToStorecoveCreditLineMapperTest extends TestCase
{
    private UblToStorecoveCreditLineMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new UblToStorecoveCreditLineMapper();
    }

    public function testMapsNormalCreditLineToNegativeInvoiceShape(): void
    {
        $mapped = $this->mapper->mapLineAmounts(100.0, 2.0, 200.0);

        $this->assertSame(2.0, $mapped['quantity']);
        $this->assertSame(-100.0, $mapped['item_price']);
        $this->assertSame(-200.0, $mapped['amount_excluding_vat']);
        $this->assertOptionalAmountExcludingTaxMatchesLineAmount($mapped);
    }

    public function testMapsOffsetClawbackLineWithPositiveWireAmounts(): void
    {
        $mapped = $this->mapper->mapLineAmounts(4590.0, -1.0, -4590.0);

        $this->assertSame(1.0, $mapped['quantity']);
        $this->assertSame(4590.0, $mapped['item_price']);
        $this->assertSame(4590.0, $mapped['amount_excluding_vat']);
        $this->assertOptionalAmountExcludingTaxMatchesLineAmount($mapped);
    }

    public function testOptionalAmountExcludingTaxUsesDiscountedLineAmountNotUnitPrice(): void
    {
        $mapped = $this->mapper->mapLineAmounts(100.0, 2.0, 180.0);

        $this->assertSame(-100.0, $mapped['item_price']);
        $this->assertSame(-180.0, $mapped['amount_excluding_vat']);
        $this->assertOptionalAmountExcludingTaxMatchesLineAmount($mapped);
    }

    public function testAmountIncludingTaxIsNotSynthesizedFromLineExtension(): void
    {
        $line = new CreditLines(
            line_id: '1',
            description: null,
            name: 'Widget',
            order_line_reference_line_id: null,
            invoice_period: null,
            item_price: 100.0,
            quantity: 2.0,
            base_quantity: null,
            quantity_unit_code: 'C62',
            allowance_charges: null,
            amount_excluding_vat: 200.0,
            amount_excluding_tax: 200.0,
            amount_including_tax: null,
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

        $this->assertNull($line->amount_including_tax, 'Exclusive-tax lines omit tax-inclusive amount when UBL does not provide one');

        $this->mapper->applyMappingOnce($line);

        $this->assertNull($line->amount_including_tax);
    }

    public function testAmountIncludingTaxNegatesProvidedGrossAmount(): void
    {
        $line = new CreditLines(
            line_id: '1',
            description: null,
            name: 'Widget',
            order_line_reference_line_id: null,
            invoice_period: null,
            item_price: 100.0,
            quantity: 2.0,
            base_quantity: null,
            quantity_unit_code: 'C62',
            allowance_charges: null,
            amount_excluding_vat: 200.0,
            amount_excluding_tax: 200.0,
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

        $this->assertSame(-238.0, $line->amount_including_tax);
        $this->assertSame(-200.0, $line->amount_excluding_vat);
    }

    public function testMapsCreditLineAllowancePositiveOnNormalLine(): void
    {
        $allowance = new AllowanceCharges(null, 20.0, null, null, null, null, 'Discount', null, 'false');

        $this->assertSame(20.0, $this->mapper->mapLineAllowanceAmount($allowance, -100.0, true));
    }

    public function testMapsCreditLineChargeNegativeOnClawbackLine(): void
    {
        $allowance = new AllowanceCharges(null, 459.0, null, null, null, null, 'Discount', null, 'true');

        $this->assertSame(-459.0, $this->mapper->mapLineAllowanceAmount($allowance, 4590.0, true));
    }

    public function testMapsCreditLineAllowancePositiveOnClawbackLine(): void
    {
        $allowance = new AllowanceCharges(null, 20.0, null, null, null, null, 'Discount', null, 'false');

        $this->assertSame(20.0, $this->mapper->mapLineAllowanceAmount($allowance, 4590.0, true));
    }

    public function testMapsCreditLineChargeNegativeOnNormalLine(): void
    {
        $allowance = new AllowanceCharges(null, 20.0, null, null, null, null, 'Discount', null, 'true');

        $this->assertSame(-20.0, $this->mapper->mapLineAllowanceAmount($allowance, -100.0, true));
    }

    public function testMapsInvoiceLineAllowanceNegative(): void
    {
        $allowance = new AllowanceCharges(null, 20.0, null, null, null, null, 'Discount', null, 'false');

        $this->assertSame(-20.0, $this->mapper->mapLineAllowanceAmount($allowance, 100.0, false));
    }

    public function testMapsCreditDocumentSurchargeChargeNegativeOnWire(): void
    {
        $charge = new AllowanceCharges(null, 25.0, null, null, null, null, 'Surcharge', null, 'true');

        $this->assertSame(-25.0, $this->mapper->mapDocumentAllowanceOrChargeAmount($charge, true));
    }

    public function testMapsCreditDocumentDiscountAllowancePositiveOnWire(): void
    {
        $allowance = new AllowanceCharges(null, 100.0, null, null, null, null, 'Discount', null, 'false');

        $this->assertSame(100.0, $this->mapper->mapDocumentAllowanceOrChargeAmount($allowance, true));
    }

    public function testMapsInvoiceDocumentSurchargeChargePositiveOnWire(): void
    {
        $charge = new AllowanceCharges(null, 25.0, null, null, null, null, 'Surcharge', null, 'true');

        $this->assertSame(25.0, $this->mapper->mapDocumentAllowanceOrChargeAmount($charge, false));
    }

    public function testApplyToCreditLineMatchesCreditNoteSignTestFixtures(): void
    {
        $line = new CreditLines(
            line_id: '1',
            description: null,
            name: 'Widget',
            order_line_reference_line_id: null,
            invoice_period: null,
            item_price: 4590.0,
            quantity: -1.0,
            base_quantity: null,
            quantity_unit_code: 'C62',
            allowance_charges: null,
            amount_excluding_vat: -4590.0,
            amount_excluding_tax: 4590.0,
            amount_including_tax: -4590.0,
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

        $this->assertSame(1.0, $line->quantity);
        $this->assertSame(4590.0, $line->item_price);
        $this->assertSame(4590.0, $line->amount_excluding_vat);
        $this->assertOptionalAmountExcludingTaxMatchesLineAmount((array) $line);
    }

    public function testApplyMappingOnceIsIdempotent(): void
    {
        $line = new CreditLines(
            line_id: '1',
            description: null,
            name: 'Widget',
            order_line_reference_line_id: null,
            invoice_period: null,
            item_price: 100.0,
            quantity: 2.0,
            base_quantity: null,
            quantity_unit_code: 'C62',
            allowance_charges: null,
            amount_excluding_vat: 200.0,
            amount_excluding_tax: 100.0,
            amount_including_tax: null,
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

        $this->assertSame(-100.0, $line->item_price);
        $this->assertSame(2.0, $line->quantity);
        $this->assertSame(-200.0, $line->amount_excluding_vat);
        $this->assertNull($line->amount_including_tax);
        $this->assertOptionalAmountExcludingTaxMatchesLineAmount((array) $line);

        $this->mapper->applyMappingOnce($line);

        $this->assertSame(-100.0, $line->item_price);
        $this->assertSame(2.0, $line->quantity);
        $this->assertSame(-200.0, $line->amount_excluding_vat);
        $this->assertOptionalAmountExcludingTaxMatchesLineAmount((array) $line);
    }

    /**
     * Storecove accepts amount_excluding_tax as an optional alias for the line
     * amount. When populated, it must match amount_excluding_vat; it is not a
     * second unit-price field.
     *
     * @param array<string, mixed> $line
     */
    private function assertOptionalAmountExcludingTaxMatchesLineAmount(array $line): void
    {
        if (($line['amount_excluding_tax'] ?? null) === null) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->assertSame(
            $line['amount_excluding_vat'],
            $line['amount_excluding_tax'],
            'When present, amount_excluding_tax must equal the signed line amount, not item_price.',
        );
    }
}
