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
use Tests\MockAccountData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Integration\Einvoice\Storecove\Support\UblStorecoveTestHarness;

/**
 * Audit probes for the Peppol + Storecove credit-note refactor.
 *
 * Each test is a question about production behaviour, not a style check.
 */
class RefactorAuditTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use UblStorecoveTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->bootUblStorecoveHarness();
    }

    /**
     * Does chargeIndicator leak into the Storecove API payload?
     */
    public function testChargeIndicatorDoesNotLeakIntoStorecoveWirePayload(): void
    {
        $client = $this->harnessClient();

        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('A', 9490, 1, 0),
            $this->harnessLineItem('B', 100, 2, 10),
            $this->harnessLineItem('C', 4590, -1, 10),
        ]);

        $ubl = $this->buildUbl($credit);
        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml']);

        $json = json_encode($wire['document']);

        $this->assertStringNotContainsString(
            'charge_indicator',
            $json,
            "chargeIndicator reached the Storecove payload:\n" . json_encode($wire['document'], JSON_PRETTY_PRINT)
        );
    }

    /**
     * Storecove line reconciliation:
     *   itemPrice * quantity / baseQuantity + allowanceCharges - amountExcludingVat ~ 0
     */
    public function testStorecoveWireLinesReconcileForDiscountedCredit(): void
    {
        $client = $this->harnessClient();

        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('A', 9490, 1, 0),
            $this->harnessLineItem('B', 100, 2, 10),
            $this->harnessLineItem('C', 4590, -1, 10),
        ]);

        $ubl = $this->buildUbl($credit);
        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml']);

        foreach ($this->wireLines($wire['document']) as $i => $line) {
            $residual = $this->residual($line);

            $this->assertEqualsWithDelta(
                0.0,
                $residual,
                0.02,
                sprintf(
                    "Storecove line %d does not reconcile (residual %s):\n%s",
                    $i + 1,
                    $residual,
                    json_encode($line, JSON_PRETTY_PRINT)
                )
            );
        }
    }

    /**
     * Negative-total Invoice emitted as a CreditNote, with a line discount.
     */
    public function testNegativeInvoiceWithLineDiscountProducesValidUbl(): void
    {
        $client = $this->harnessClient();

        $invoice = $this->harnessInvoice($client, [
            $this->harnessLineItem('N1', 100, -2, 10),
        ]);

        $ubl = $this->buildUbl($invoice);

        $this->assertTrue($ubl['is_credit_note'], 'Negative invoice should emit a CreditNote.');

        foreach ($ubl['ubl_lines'] as $i => $line) {
            $this->assertUblLineSatisfiesR120($line, 'negative invoice line ' . ($i + 1));
        }

        $this->assertUblPassesSchematron($ubl['xml'], 'negative invoice with line discount');
    }

    /**
     * Negative unit cost with a line discount on a positive credit.
     */
    public function testNegativeCostWithDiscountProducesValidUbl(): void
    {
        $client = $this->harnessClient();

        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('P', 9490, 1, 0),
            $this->harnessLineItem('NC', -100, 2, 10),
        ]);

        $ubl = $this->buildUbl($credit);

        foreach ($ubl['ubl_lines'] as $i => $line) {
            $this->assertUblLineSatisfiesR120($line, 'negative-cost line ' . ($i + 1));
        }

        $this->assertUblPassesSchematron($ubl['xml'], 'negative cost with line discount');
    }

    /**
     * Flat-amount line discount on an offset row.
     */
    public function testAmountDiscountOnOffsetRowProducesValidUbl(): void
    {
        $client = $this->harnessClient();

        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('A', 9490, 1, 0, 10, true),
            $this->harnessLineItem('C', 4590, -1, 20, 10, true),
        ]);

        $ubl = $this->buildUbl($credit);

        foreach ($ubl['ubl_lines'] as $i => $line) {
            $this->assertUblLineSatisfiesR120($line, 'amount-discount line ' . ($i + 1));
        }

        $this->assertUblPassesSchematron($ubl['xml'], 'flat amount discount on offset row');
    }

    /**
     * applyMappingOnce() must be idempotent — CreditLines ctor already maps once.
     */
    public function testCreditLineMapperIsIdempotent(): void
    {
        $mapper = new \App\Services\EDocument\Gateway\Storecove\UblToStorecoveCreditLineMapper();

        $line = new \App\Services\EDocument\Gateway\Storecove\Models\CreditLines(
            line_id: '1',
            description: 'd',
            name: 'n',
            order_line_reference_line_id: null,
            invoice_period: null,
            item_price: 100.0,
            quantity: 2.0,
            base_quantity: null,
            quantity_unit_code: 'C62',
            allowance_charges: null,
            amount_excluding_vat: 200.0,
            amount_excluding_tax: 100.0,
            amount_including_tax: 220.0,
            taxes_duties_fees: null,
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

        $afterCtor = [
            'item_price' => $line->item_price,
            'quantity' => $line->quantity,
            'amount_excluding_vat' => $line->amount_excluding_vat,
            'amount_excluding_tax' => $line->amount_excluding_tax,
            'amount_including_tax' => $line->amount_including_tax,
        ];

        $mapper->applyMappingOnce($line);

        $this->assertSame(
            $afterCtor,
            [
                'item_price' => $line->item_price,
                'quantity' => $line->quantity,
                'amount_excluding_vat' => $line->amount_excluding_vat,
                'amount_excluding_tax' => $line->amount_excluding_tax,
                'amount_including_tax' => $line->amount_including_tax,
            ],
            'applyMappingOnce() is not idempotent — a second call must not re-negate the line.'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function wireLines(array $document): array
    {
        $lines = $document['invoice_lines']
            ?? $document['document']['invoice']['invoice_lines']
            ?? [];

        $this->assertNotEmpty($lines, 'No wire lines found - probe would be vacuous.');

        return $lines;
    }

    private function residual(array $line): float
    {
        $price = (float) ($line['item_price'] ?? 0);
        $qty = (float) ($line['quantity'] ?? 0);
        $base = (float) ($line['base_quantity'] ?? 1) ?: 1.0;
        $amount = (float) ($line['amount_excluding_vat'] ?? 0);

        $allowances = 0.0;
        foreach ($line['allowance_charges'] ?? [] as $ac) {
            $allowances += (float) ($ac['amount_excluding_tax'] ?? 0);
        }

        return ($price * $qty / $base) + $allowances - $amount;
    }
}
