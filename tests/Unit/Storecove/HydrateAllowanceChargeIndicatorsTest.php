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
use App\Services\EDocument\Gateway\Storecove\Models\Credit;
use App\Services\EDocument\Gateway\Storecove\Models\CreditLines;
use App\Services\EDocument\Gateway\Storecove\Models\AllowanceCharges;
use App\Services\EDocument\Gateway\Storecove\StorecoveAdapter;
use App\Services\EDocument\Gateway\Storecove\Storecove;

class HydrateAllowanceChargeIndicatorsTest extends TestCase
{
    public function testHydratesLineLevelChargeIndicatorFromUblXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<CreditNote xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2">
    <cac:CreditNoteLine>
        <cac:AllowanceCharge>
            <cbc:ChargeIndicator>true</cbc:ChargeIndicator>
            <cbc:Amount currencyID="EUR">459.00</cbc:Amount>
        </cac:AllowanceCharge>
    </cac:CreditNoteLine>
</CreditNote>
XML;

        $allowance = new AllowanceCharges(null, 459.0, null, null, null, null, 'Discount', null, 'false');
        $line = new CreditLines(
            line_id: '1',
            description: null,
            name: 'Offset',
            order_line_reference_line_id: null,
            invoice_period: null,
            item_price: 4590.0,
            quantity: -1.0,
            base_quantity: null,
            quantity_unit_code: 'C62',
            allowance_charges: [$allowance],
            amount_excluding_vat: -4131.0,
            amount_excluding_tax: null,
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

        $credit = (new \ReflectionClass(Credit::class))->newInstanceWithoutConstructor();
        $credit->invoice_lines = [$line];

        $adapter = new StorecoveAdapter(new Storecove());
        $reflection = new \ReflectionClass($adapter);
        $property = $reflection->getProperty('storecove_invoice');
        $property->setAccessible(true);
        $property->setValue($adapter, $credit);

        $method = $reflection->getMethod('hydrateAllowanceChargeIndicatorsFromUblXml');
        $method->setAccessible(true);
        $method->invoke($adapter, $xml);

        $this->assertSame('true', $line->allowance_charges[0]->getChargeIndicator());

        $document = $adapter->getDocument();
        $wireJson = json_encode($document['document']);

        $this->assertStringNotContainsString(
            'charge_indicator',
            $wireJson,
            'Internal hydration field must not appear in Storecove wire JSON'
        );
        $this->assertStringNotContainsString(
            'storecove_credit_mapped',
            $wireJson,
            'Internal credit mapping state must not appear in Storecove wire JSON'
        );
    }
}
