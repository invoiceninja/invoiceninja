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
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\DataMapper\CompanySettings;
use Tests\MockAccountData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Integration\Einvoice\Storecove\Support\UblStorecoveTestHarness;

/**
 * Extended UBL → Storecove scenarios beyond the canonical regression suite.
 *
 * Wire expectations are derived from parsed UBL via UblStorecoveTestHarness — never calc().
 *
 * Scenarios still pending product support (skipped until implemented):
 *   - inclusive tax + mixed discount + offset (Peppol path is exclusive-tax only today)
 */
class UblToStorecoveRegressionGapTest extends TestCase
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

    public function testInclusiveTaxMixedDiscountCreditMapsFromUbl(): void
    {
        $this->markTestSkipped('Peppol UBL generation supports exclusive tax only — inclusive lines fail R120 on Primary');

        $client = $this->harnessClient();
        $credit = $this->harnessInclusiveCredit($client, [
            $this->harnessLineItem('Primary', 110, 2, 10),
            $this->harnessLineItem('Offset', 55, -1, 10),
        ]);

        $this->assertTrue((bool) $credit->uses_inclusive_taxes);
        $this->assertGreaterThan(0, (float) $credit->amount, 'Positive net credit with primary + offset');

        $ubl = $this->buildUbl($credit);

        $offsetLine = $ubl['ubl_lines'][1];
        $this->assertSame('true', $offsetLine['allowance_charge']['charge_indicator'] ?? '', 'Offset discount on positive credit must be UBL line charge');
        $this->assertUblLineSatisfiesR040($offsetLine, 'inclusive offset');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];

        $this->assertCount(2, $wire['invoice_lines']);
        $this->assertGreaterThan(0, $wire['invoice_lines'][1]['item_price'], 'Clawback line price must be positive on wire');
        $this->assertEqualsWithDelta(
            -abs($offsetLine['allowance_charge']['amount']),
            $this->wireAllowanceSum($wire['invoice_lines'][1]),
            0.01,
            'UBL line charge → negative wire allowance on clawback line'
        );

        // Full contract — expected to fail until inclusive-tax offset lines reconcile in UBL + wire.
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);
        $this->assertUblPassesSchematron($ubl['xml'], 'inclusive tax mixed discount credit');
    }

    public function testMultiCurrencyCreditWithLineDiscountMapsFromUbl(): void
    {
        $client = $this->harnessForeignCurrencyClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Foreign', 100, 1, 10),
        ]);

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'multi-currency discounted credit');
        $this->assertStringContainsString('currencyID="USD"', $ubl['xml'], 'UBL must carry client currency');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);
        $this->assertNotEmpty($wire['invoice_lines'][0]['allowance_charges'] ?? [], 'Line discount must appear on wire');
    }

    public function testSingleLineOffsetOnlyCreditWithDiscountMapsFromUbl(): void
    {
        // Negative document total: line allowance (not charge) with positive CreditedQuantity for R120.
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Offset only', 4590, -1, 10),
        ]);

        $this->assertLessThan(0, (float) $credit->amount, 'Single offset-only credit has negative document total');

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'single offset-only discounted credit');

        $line = $ubl['ubl_lines'][0];
        $this->assertGreaterThan(0, $line['quantity'], 'Negative-total discounted line derives positive CreditedQuantity');
        $this->assertGreaterThan(0, $line['line_extension_amount'], 'LineExtensionAmount sign matches negative-total allowance route');
        $this->assertSame('false', $line['allowance_charge']['charge_indicator'] ?? 'false', 'No line charge when document total is negative');
        $this->assertUblLineSatisfiesR040($line, 'offset-only');
        $this->assertStringNotContainsString(
            '<cbc:ChargeIndicator>true</cbc:ChargeIndicator>',
            $ubl['xml'],
            'Single negative-total credit must not emit line charge'
        );

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];

        $this->assertCount(1, $wire['invoice_lines']);
        $this->assertLessThan(0, $wire['invoice_lines'][0]['item_price'], 'Normal credit line shape (negative item_price on wire)');
        $this->assertEqualsWithDelta(
            abs($line['allowance_charge']['amount']),
            $this->wireAllowanceSum($wire['invoice_lines'][0]),
            0.01,
            'Allowance (not charge) → positive wire discount on normal credit line'
        );

        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);
    }

    public function testNegativeTotalPercentageLineDiscountDerivesQuantityAndMapsToWire(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Pct', 100, -2, 10),
        ]);

        $this->assertLessThan(0, (float) $credit->amount);
        $this->assertEqualsWithDelta(-180.0, (float) $credit->line_items[0]->line_total, 0.01);

        $ubl = $this->buildUbl($credit);
        $line = $ubl['ubl_lines'][0];

        $this->assertEqualsWithDelta(2.0, $line['quantity'], 0.001, 'Percentage discount re-derives positive CreditedQuantity');
        $this->assertEqualsWithDelta(180.0, $line['line_extension_amount'], 0.01, 'LEA = |qty| × price − line allowance');
        $this->assertSame('false', $line['allowance_charge']['charge_indicator'] ?? 'false', 'Percentage on negative-total doc is a line allowance');
        $this->assertUblLineSatisfiesR120($line, 'percentage negative-total line');
        $this->assertUblPassesSchematron($ubl['xml'], 'percentage negative-total line');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);
    }

    public function testNegativeTotalOpposingLinePercentageDiscountSatisfiesR041(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Primary', 100, -5),
            $this->harnessLineItem('Opposing', 100, 2, 10),
        ]);

        $this->assertLessThan(0, (float) $credit->amount);

        $ubl = $this->buildUbl($credit);
        $opposingLine = $ubl['ubl_lines'][1];

        $this->assertEqualsWithDelta(-2.0, $opposingLine['quantity'], 0.001, 'Opposing line on negative-total doc keeps real qty with economic sign');
        $this->assertEqualsWithDelta(-180.0, $opposingLine['line_extension_amount'], 0.01);
        $this->assertSame('true', $opposingLine['allowance_charge']['charge_indicator'] ?? '', 'Opposing percentage discount increases magnitude → line charge');
        $this->assertUblLineSatisfiesR120($opposingLine, 'opposing line');
        $this->assertUblLineSatisfiesR040($opposingLine, 'opposing line');
        $this->assertUblPassesSchematron($ubl['xml'], 'negative-total opposing line percentage discount');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);
    }

    public function testPositiveTotalFlatDiscountOnClawbackUsesAllowanceNotCharge(): void
    {
        $client = $this->harnessClient();
        $credit = Credit::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => true,
            'line_items' => [
                $this->harnessLineItem('A', 9490, 1, 0, 0, true),
                $this->harnessLineItem('C', 4590, -1, 20, 0, true),
            ],
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ])->calc()->getCredit();

        CreditInvitation::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_contact_id' => $client->contacts()->first()->id,
            'credit_id' => $credit->id,
        ]);

        $credit = $credit->fresh(['invitations']);
        $this->assertGreaterThan(0, (float) $credit->amount, 'Primary line keeps document total positive');
        $this->assertEqualsWithDelta(-4610.0, (float) $credit->line_items[1]->line_total, 0.01);

        $ubl = $this->buildUbl($credit);
        $offsetLine = $ubl['ubl_lines'][1];

        $this->assertEqualsWithDelta(-1.0, $offsetLine['quantity'], 0.001);
        $this->assertEqualsWithDelta(-4610.0, $offsetLine['line_extension_amount'], 0.01);
        $this->assertSame('false', $offsetLine['allowance_charge']['charge_indicator'] ?? 'false', 'Flat discount on clawback increases magnitude → allowance, not charge');
        $this->assertEqualsWithDelta(20.0, $offsetLine['allowance_charge']['amount'], 0.01);
        $this->assertUblLineSatisfiesR120($offsetLine, 'flat clawback');
        $this->assertUblPassesSchematron($ubl['xml'], 'flat amount discount on positive-total clawback');

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);

        $wireOffset = $wire['invoice_lines'][1];
        $this->assertEqualsWithDelta(20.0, $this->wireAllowanceSum($wireOffset), 0.01, 'UBL allowance → positive wire discount on clawback line');
    }

    public function testNegativeTotalFlatAmountLineDiscountPreservesCreditedQuantity(): void
    {
        $client = $this->harnessClient();
        $credit = Credit::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => true,
            'line_items' => [
                $this->harnessLineItem('Flat', 100, -2, 20, 10, true),
            ],
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ])->calc()->getCredit();

        CreditInvitation::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_contact_id' => $client->contacts()->first()->id,
            'credit_id' => $credit->id,
        ]);

        $credit = $credit->fresh(['invitations']);
        $this->assertLessThan(0, (float) $credit->amount);
        $this->assertEqualsWithDelta(-2.0, (float) $credit->line_items[0]->quantity, 0.001);

        $ubl = $this->buildUbl($credit);
        $line = $ubl['ubl_lines'][0];

        $this->assertEqualsWithDelta(
            2.0,
            abs($line['quantity']),
            0.001,
            'Flat amount discount must preserve commercial CreditedQuantity on negative-total documents'
        );
        $this->assertEqualsWithDelta(220.0, $line['line_extension_amount'], 0.01, 'LEA = |qty| × price + flat line charge');
        $this->assertSame('true', $line['allowance_charge']['charge_indicator'] ?? '', 'Flat amount on negative-total doc is a line charge');
        $this->assertUblLineSatisfiesR120($line, 'flat amount negative-total line');
        $this->assertUblPassesSchematron($ubl['xml'], 'flat amount negative-total line');
    }

    /**
     * Peppol builder does not emit line-level non-discount charges from line items.
     * Document-level custom surcharges use ChargeIndicator=true and are the supported charge shape.
     */
    public function testPeppolSurchargeTaxedWithoutExplicitFlagViaRepositorySave(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Base', 500, 1),
        ]);
        $credit->custom_surcharge1 = 25;
        $credit->custom_surcharge_tax1 = false;
        $credit = $this->harnessSaveCredit($credit);

        $this->assertTrue((bool) $credit->custom_surcharge_tax1, 'PEPPOL repository save forces surcharge tax flags');

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'PEPPOL auto-taxed document surcharge');
    }

    public function testNonDiscountLineChargeMapsFromUbl(): void
    {
        $client = $this->harnessClient();
        $credit = $this->harnessCredit($client, [
            $this->harnessLineItem('Base', 500, 1),
        ]);
        $credit->custom_surcharge1 = 25;
        $credit = $this->harnessSaveCredit($credit);

        $ubl = $this->buildUbl($credit);
        $this->assertUblPassesSchematron($ubl['xml'], 'document surcharge credit');

        $docCharges = $this->parseUblDocumentAllowances($ubl['xml']);
        $this->assertNotEmpty($docCharges, 'UBL must contain document-level AllowanceCharge');
        $surcharge = collect($docCharges)->first(fn ($ac) => ($ac['charge_indicator'] ?? 'false') === 'true');
        $this->assertNotNull($surcharge, 'Document surcharge must be ChargeIndicator=true');
        $this->assertEqualsWithDelta(25.0, $surcharge['amount'], 0.01);

        $wire = $this->buildWire($credit, $ubl['peppol'], $ubl['xml'])['document'];
        $this->assertAllCreditWireLinesMatchUbl($ubl['ubl_lines'], $wire['invoice_lines']);
        $this->assertCreditWireHeaderMatchesUbl($ubl['ubl_totals'], $wire, $ubl['xml']);
    }

    /**
     * @param  array<int, \App\DataMapper\InvoiceItem>  $lineItems
     */
    private function harnessInclusiveCredit(Client $client, array $lineItems): Credit
    {
        $credit = Credit::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'uses_inclusive_taxes' => true,
            'discount' => 0,
            'is_amount_discount' => false,
            'line_items' => $lineItems,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ])->calc()->getCredit();

        CreditInvitation::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_contact_id' => $client->contacts()->first()->id,
            'credit_id' => $credit->id,
        ]);

        return $credit->fresh(['invitations']);
    }

    private function harnessForeignCurrencyClient(): Client
    {
        $client = $this->harnessClient();

        $settings = $client->settings ?? CompanySettings::defaults();
        $settings->currency_id = '1';

        $client->settings = $settings;
        $client->save();

        return $client->fresh();
    }

}
