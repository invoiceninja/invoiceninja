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

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\Credit;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Integration\Einvoice\Storecove\Support\UblStorecoveTestHarness;

/**
 * Canonical UBL-first regression suite for the Peppol → Storecove pipeline.
 *
 * Every wire assertion is derived from parsed validated UBL fields via
 * UblStorecoveTestHarness::expectedCreditWireFromUbl() — never from calc().
 *
 * Scenarios covered:
 *  - simple credit (no discount)
 *  - discounted credit (positive qty)
 *  - offset clawback (negative qty, no discount)
 *  - offset + percentage discount (UBL line charge)
 *  - mixed-sign multi-line credit
 *  - three-line discount scenario (production repro case)
 *  - positive invoice + discount (control path)
 *  - negative invoice + discount (credit-note route)
 *  - credit vs negative-invoice wire equivalence
 *  - document header totals vs UBL LegalMonetaryTotal
 *  - schematron validation on all credit scenarios
 */
class UblToStorecoveRegressionTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use UblStorecoveTestHarness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->bootUblStorecoveHarness();
    }

    public function testSimpleCreditNoDiscountMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Widget', 100, 2),
        ]);

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'simple credit');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];

        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);

        $line = $wire['invoice_lines'][0];
        $this->assertGreaterThan(0, $line['quantity']);
        $this->assertLessThan(0, $line['item_price']);
        $this->assertLessThan(0, $line['amount_excluding_vat']);
        $this->assertLessThan(0, $wire['amount_including_vat']);
    }

    public function testDiscountedCreditPositiveQuantityMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Discounted', 671.2, 1, 20),
        ]);

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'discounted credit');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];

        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertNotEmpty($wire['invoice_lines'][0]['allowance_charges'] ?? [], 'Discount must appear on wire');
        $this->assertSame('false', $ubl['ubl_lines'][0]['allowance_charge']['charge_indicator'] ?? 'false');
    }

    public function testOffsetClawbackNoDiscountMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Primary', 9490, 1),
            $this->harnessLineItem('Offset', 4590, -1),
        ]);

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'offset clawback');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];

        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);

        $this->assertEqualsWithDelta(-1.0, $ubl['ubl_lines'][1]['quantity'], 0.001);
        $this->assertGreaterThan(0, $wire['invoice_lines'][1]['item_price'], 'Clawback line price must be positive on wire');
        $this->assertGreaterThan(0, $wire['invoice_lines'][1]['amount_excluding_vat'], 'Clawback line amount must be positive on wire');
    }

    public function testOffsetWithPercentageDiscountUsesUblChargeAndMapsToWire(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('A', 9490, 1, 0),
            $this->harnessLineItem('B', 100, 2, 10),
            $this->harnessLineItem('C', 4590, -1, 10),
        ], markSent: true);

        $this->assertEqualsWithDelta(6092.90, (float) $credit->amount, 0.01);

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'offset + discount');

        $offsetLine = $ubl['ubl_lines'][2];
        $this->assertSame('C', $offsetLine['name']);
        $this->assertEqualsWithDelta(-1.0, $offsetLine['quantity'], 0.001);
        $this->assertSame('true', $offsetLine['allowance_charge']['charge_indicator'], 'Offset discount must be UBL line charge (R040)');
        $this->assertEqualsWithDelta(459.0, $offsetLine['allowance_charge']['amount'], 0.01);

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);

        $wireOffset = $wire['invoice_lines'][2];
        $this->assertEqualsWithDelta(1.0, $wireOffset['quantity'], 0.001);
        $this->assertEqualsWithDelta(-459.0, $this->wireAllowanceSum($wireOffset), 0.01, 'UBL charge → negative wire allowance on clawback line');
    }

    public function testNegativeCostWithDiscountProjectsEconomicSignAndMapsToWire(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Primary', 9490, 1, 0),
            $this->harnessLineItem('NC', -100, 2, 10),
        ]);

        $this->assertGreaterThan(0, (float) $credit->amount, 'Primary line must dominate negative-cost row');

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'negative cost with discount');

        $ncLine = $ubl['ubl_lines'][1];
        $this->assertEqualsWithDelta(-2.0, $ncLine['quantity'], 0.001, 'Negative cost must project into negative CreditedQuantity');
        $this->assertEqualsWithDelta(100.0, $ncLine['price_amount'], 0.01);
        $this->assertSame('true', $ncLine['allowance_charge']['charge_indicator'] ?? '', 'Discount on negative-extension row must be line charge');
        $this->assertUblLineSatisfiesR120($ncLine, 'negative cost');
        $this->assertUblLineSatisfiesR040($ncLine, 'negative cost');
        $this->assertEqualsWithDelta(-180.0, $ncLine['line_extension_amount'], 0.01);

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
    }

    public function testMixedSignFourLineCreditMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Item A', 9490, 1, 0, 21),
            $this->harnessLineItem('Item B', 4590, -1, 0, 21),
            $this->harnessLineItem('Item C', 400, 1, 0, 21),
            $this->harnessLineItem('Item D', 100, 1, 0, 21),
        ]);

        $this->assertEqualsWithDelta(6534.0, (float) $credit->amount, 0.05, '5400 net + 21% VAT');

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'mixed sign credit');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);

        $lineSum = array_sum(array_column($wire['invoice_lines'], 'amount_excluding_vat'));
        $this->assertEqualsWithDelta(-5400.0, $lineSum, 0.05, 'Wire lines must net to UBL tax-exclusive total');
        $this->assertEqualsWithDelta(4590.0, $wire['invoice_lines'][1]['amount_excluding_vat'], 0.01, 'Offset must not become another credit');
    }

    public function testPositiveInvoiceWithDiscountMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $invoice = $this->harnessInvoice($client, [
            $this->harnessLineItem('Invoiced', 671.2, 1, 20),
        ]);

        $ubl = $this->buildUbl($invoice);
        $this->assertFalse($ubl['is_credit_note']);

        $wire = $this->buildWire($invoice, $ubl['peppol'], $ubl['xml'])['document'];
        $ublLine = $ubl['ubl_lines'][0];
        $wireLine = $wire['invoice_lines'][0];

        $this->assertUblLineSatisfiesR120($ublLine, 'invoice');
        $this->assertWireLineMatchesUblInvoiceMapping($ublLine, $wireLine, 'invoice');
        $this->assertGreaterThan(0, $wireLine['item_price']);
        $this->assertGreaterThan(0, $wire['amount_including_vat']);
    }

    public function testNegativeInvoiceNoDiscountMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $invoice = $this->harnessInvoice($client, [
            $this->harnessLineItem('Negative', -100, 2),
        ]);

        $this->assertTrue($invoice->amount < 0);

        $ubl = $this->buildUbl($invoice);
        $this->assertTrue($ubl['is_credit_note']);

        $wire = $this->buildWire($invoice, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
    }

    public function testNegativeInvoiceWithDiscountMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $invoice = $this->harnessInvoice($client, [
            $this->harnessLineItem('Negative', -671.2, 1, 20),
        ]);

        $this->assertTrue($invoice->amount < 0);

        $ubl = $this->buildUbl($invoice);
        $this->assertUblLineSatisfiesR120($ubl['ubl_lines'][0], 'negative invoice discount');
        $this->assertUblLineSatisfiesR040($ubl['ubl_lines'][0], 'negative invoice discount');

        $wire = $this->buildWire($invoice, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
    }

    public function testCreditAndNegativeInvoiceProduceEquivalentWireFromUbl(): void
    {
        $client = $this->harnessClient();

        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Equiv', 100, 2),
        ]);

        $negativeInvoice = $this->harnessInvoice($client, [
            $this->harnessLineItem('Equiv', -100, 2),
        ]);

        $creditUbl = $this->buildUbl($credit);
        $invoiceUbl = $this->buildUbl($negativeInvoice);

        $creditWire = $this->buildWire($credit, $creditUbl['peppol'], $creditUbl['xml'])['document']['invoice_lines'][0];
        $invoiceWire = $this->buildWire($negativeInvoice, $invoiceUbl['peppol'], $invoiceUbl['xml'])['document']['invoice_lines'][0];

        foreach (['quantity', 'item_price', 'amount_excluding_vat'] as $field) {
            $this->assertEqualsWithDelta(
                $creditWire[$field],
                $invoiceWire[$field],
                0.01,
                "Credit and negative invoice must produce identical wire {$field}"
            );
        }
    }

    public function testAmountDiscountOnCreditMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('FixedDisc', 200, 1, 15, 10, true),
        ]);

        $ubl = $this->buildUbl($credit);
        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];

        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
    }

    public function testDocumentLevelDiscountOnCreditMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Line', 1000, 1),
        ]);
        $credit->discount = 10;
        $credit->is_amount_discount = false;
        $credit = $credit->calc()->getCredit();
        $credit->save();

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'document discount credit');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);
    }

    public function testAllCreditScenariosPassSchematron(): void
    {
        if (!$this->harnessHasSaxon) {
            $this->markTestSkipped('Saxon not installed');
        }

        $client = $this->harnessClient();
        $scenarios = [
            'simple' => [$this->harnessLineItem('S', 50, 1)],
            'discounted' => [$this->harnessLineItem('D', 100, 2, 10)],
            'offset' => [$this->harnessLineItem('P', 100, 1), $this->harnessLineItem('O', 50, -1)],
            'offset_disc' => [$this->harnessLineItem('P', 100, 1), $this->harnessLineItem('O', 50, -1, 10)],
        ];

        foreach ($scenarios as $name => $items) {
            $credit = $this->harnessCredit($client, $items);
            $ubl = $this->buildUbl($credit);
            $this->assertUblPassesSchematron($ubl['xml'], $name);
        }
    }

    public function testValidatedUblChargeIndicatorIsTrueForOffsetDiscountLine(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Primary', 9490, 1, 0),
            $this->harnessLineItem('Offset', 4590, -1, 10),
        ]);

        $ubl = $this->buildUbl($credit);
        $offsetLine = $ubl['ubl_lines'][1];

        $this->assertStringContainsString('<cbc:ChargeIndicator>true</cbc:ChargeIndicator>', $ubl['xml']);
        $this->assertSame('true', $offsetLine['allowance_charge']['charge_indicator']);

        $storecove = new Storecove();
        $storecove->adapter->transformFromPeppol(
            $credit,
            $ubl['peppol']->getDocument(),
            $ubl['peppol']->getDocumentKind(),
            $ubl['xml'],
        );

        $line = $storecove->adapter->getInvoice()->getInvoiceLines()[1];
        $this->assertNotEmpty($line->allowance_charges);
        $this->assertSame(
            'true',
            $line->allowance_charges[0]->getChargeIndicator(),
            'ChargeIndicator must be hydrated from validated UBL bytes after deserialize'
        );

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml']);
        $this->assertStringNotContainsString(
            'charge_indicator',
            json_encode($wire['document']),
            'Internal hydration field must not appear in Storecove wire JSON'
        );
    }

    public function testWireLinesWithoutDiscountHaveNoAllowanceCharges(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Plain', 250, 3),
        ]);

        $ubl = $this->buildUbl($credit);
        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];

        $this->assertArrayNotHasKey('allowance_charge', $ubl['ubl_lines'][0]);
        $this->assertEmpty($wire['invoice_lines'][0]['allowance_charges'] ?? []);
    }
}
