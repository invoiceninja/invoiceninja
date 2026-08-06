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

namespace Tests\Unit\Services\Quickbooks;

use App\DataMapper\QuickbooksSettings;
use App\Factory\InvoiceItemFactory;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Services\Quickbooks\Models\QbInvoice;
use App\Services\Quickbooks\QuickbooksService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use ReflectionClass;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Fixture-based guards for AST tax write-back (future AstTaxResponseProcessor extraction).
 * Independent of live QBUS — invokes processQuickbooksTaxResponse via reflection.
 */
class AstTaxResponseProcessorTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->app['config']->set('services.quickbooks.client_id', null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ast_disabled_leaves_invoice_taxes_unchanged(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 0),
        ]);
        $invoice->tax_name1 = 'Manual';
        $invoice->tax_rate1 = 5;
        $invoice->saveQuietly();

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: false);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 8.25,
            tax_lines: [$this->taxLine(8.25, 8.25, 100)],
            lines: [$this->salesLine('TAX')],
        ), $invoice);

        $invoice = $invoice->fresh();
        $this->assertSame('Manual', $invoice->tax_name1);
        $this->assertEquals(5.0, (float) $invoice->tax_rate1);
    }

    public function test_ast_writes_aggregated_tax_to_taxable_line_items(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 0),
        ]);

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 8.25,
            tax_lines: [
                $this->taxLine(5.0, 5.0, 100),
                $this->taxLine(3.25, 3.25, 100),
            ],
            lines: [$this->salesLine('TAX')],
            total_amt: 108.25,
        ), $invoice);

        $invoice = $invoice->fresh();
        $line = $invoice->line_items[0];

        $this->assertEqualsWithDelta(8.25, (float) $line->tax_rate1, 0.01);
        $this->assertNotEmpty($line->tax_name1);
        $this->assertSame('', $invoice->tax_name1);
        $this->assertEquals(0.0, (float) $invoice->tax_rate1);
    }

    public function test_ast_mixed_taxable_and_exempt_lines(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Taxable', 150, tax_rate: 0),
            $this->lineItem('Exempt', 100, tax_rate: 0, tax_id: '5'),
            $this->lineItem('Taxable2', 250, tax_rate: 0),
        ]);

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 33.0,
            tax_lines: [$this->taxLine(33.0, 8.25, 400)],
            lines: [
                $this->salesLine('TAX'),
                $this->salesLine('NON'),
                $this->salesLine('TAX'),
            ],
            total_amt: 433.0,
        ), $invoice);

        $invoice = $invoice->fresh();
        $lines = $invoice->line_items;

        $this->assertGreaterThan(0, (float) $lines[0]->tax_rate1);
        $this->assertEquals(0.0, (float) $lines[1]->tax_rate1);
        $this->assertSame('', $lines[1]->tax_name1);
        $this->assertGreaterThan(0, (float) $lines[2]->tax_rate1);
    }

    public function test_ast_all_exempt_clears_taxes(): void
    {
        $line = $this->lineItem('Exempt', 100, tax_rate: 6);
        $line->tax_name1 = 'Old Tax';

        $invoice = $this->makeInvoiceWithLines([$line]);
        $invoice->tax_name1 = 'Old Invoice Tax';
        $invoice->tax_rate1 = 6;
        $invoice->saveQuietly();

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 0,
            tax_lines: [],
            lines: [$this->salesLine('NON')],
            total_amt: 100,
        ), $invoice);

        $invoice = $invoice->fresh();
        $this->assertSame('', $invoice->tax_name1);
        $this->assertEquals(0.0, (float) $invoice->tax_rate1);
        $this->assertSame('', $invoice->line_items[0]->tax_name1);
        $this->assertEquals(0.0, (float) $invoice->line_items[0]->tax_rate1);
    }

    public function test_ast_creates_tax_rate_record_when_missing(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 0),
        ]);
        $this->client->state = 'CA';
        $this->client->save();

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 7.25,
            tax_lines: [$this->taxLine(7.25, 7.25, 100)],
            lines: [$this->salesLine('TAX')],
            total_amt: 107.25,
        ), $invoice);

        $invoice = $invoice->fresh();
        $tax_name = $invoice->line_items[0]->tax_name1;
        $tax_rate = (float) $invoice->line_items[0]->tax_rate1;

        $this->assertNotEmpty($tax_name);
        $this->assertTrue(
            TaxRate::query()
                ->where('company_id', $this->company->id)
                ->where('name', $tax_name)
                ->where('rate', $tax_rate)
                ->exists()
        );
    }

    public function test_ast_validates_and_syncs_mismatched_amounts(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 0),
        ]);
        // Force a deliberate mismatch before AST processing finishes amount sync
        $invoice->amount = 90.00;
        $invoice->total_taxes = 0;
        $invoice->saveQuietly();

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 8.25,
            tax_lines: [$this->taxLine(8.25, 8.25, 100)],
            lines: [$this->salesLine('TAX')],
            total_amt: 108.25,
        ), $invoice);

        $invoice = $invoice->fresh();
        $this->assertEqualsWithDelta(108.25, (float) $invoice->amount, 0.01);
        $this->assertEqualsWithDelta(8.25, (float) $invoice->total_taxes, 0.01);
    }

    public function test_ast_normalizes_single_tax_line_object(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 0),
        ]);

        $qb_response = [
            'TotalAmt' => 106.0,
            'TxnTaxDetail' => [
                'TotalTax' => 6.0,
                // Single object, not array — production normalizes this
                'TaxLine' => $this->taxLine(6.0, 6.0, 100),
            ],
            'Line' => $this->salesLine('TAX'),
        ];

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $qb_response, $invoice);

        $invoice = $invoice->fresh();
        $this->assertEqualsWithDelta(6.0, (float) $invoice->line_items[0]->tax_rate1, 0.01);
    }

    public function test_ast_skips_non_sales_item_lines_when_assigning_taxes(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 0),
        ]);

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, [
            'TotalAmt' => 106.0,
            'TxnTaxDetail' => [
                'TotalTax' => 6.0,
                'TaxLine' => [$this->taxLine(6.0, 6.0, 100)],
            ],
            'Line' => [
                [
                    'DetailType' => 'DiscountLineDetail',
                    'Amount' => 5,
                ],
                $this->salesLine('TAX'),
            ],
        ], $invoice);

        $invoice = $invoice->fresh();
        $this->assertEqualsWithDelta(6.0, (float) $invoice->line_items[0]->tax_rate1, 0.01);
    }

    public function test_ast_adjusts_client_balance_for_sent_invoice_when_totals_change(): void
    {
        $invoice = $this->makeSentInvoiceForAstBalanceTest(initial_balance: 100.0, client_balance: 100.0);

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 8.25,
            tax_lines: [$this->taxLine(8.25, 8.25, 100)],
            lines: [$this->salesLine('TAX')],
            total_amt: 108.25,
        ), $invoice);

        $invoice = $invoice->fresh();
        $client = $this->client->fresh();

        $this->assertEqualsWithDelta(108.25, (float) $invoice->amount, 0.01);
        $this->assertEqualsWithDelta(108.25, (float) $invoice->balance, 0.01);
        $this->assertEqualsWithDelta(108.25, (float) $client->balance, 0.01);
    }

    public function test_ast_does_not_adjust_client_balance_for_draft_invoice(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 0),
        ]);
        $invoice->status_id = Invoice::STATUS_DRAFT;
        $invoice->amount = 100;
        $invoice->balance = 100;
        $invoice->saveQuietly();

        $this->client->balance = 0;
        $this->client->saveQuietly();

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 8.25,
            tax_lines: [$this->taxLine(8.25, 8.25, 100)],
            lines: [$this->salesLine('TAX')],
            total_amt: 108.25,
        ), $invoice);

        $invoice = $invoice->fresh();
        $client = $this->client->fresh();

        $this->assertEqualsWithDelta(108.25, (float) $invoice->amount, 0.01);
        $this->assertEquals(0.0, (float) $client->balance);
    }

    public function test_ast_does_not_adjust_client_balance_when_sent_invoice_totals_unchanged(): void
    {
        $invoice = $this->makeSentInvoiceForAstBalanceTest(initial_balance: 106.0, client_balance: 106.0);
        $invoice->amount = 106.0;
        $invoice->total_taxes = 6.0;
        $invoice->balance = 106.0;
        $invoice->saveQuietly();

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $this->invokeProcessTax($qb_invoice, $this->qbResponseWithTax(
            total_tax: 6.0,
            tax_lines: [$this->taxLine(6.0, 6.0, 100)],
            lines: [$this->salesLine('TAX')],
            total_amt: 106.0,
        ), $invoice);

        $client = $this->client->fresh();

        $this->assertEqualsWithDelta(106.0, (float) $client->balance, 0.01);
    }

    public function test_validate_and_sync_amounts_within_tolerance_does_not_change_invoice(): void
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 6),
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $original_amount = (float) $invoice->amount;
        $original_tax = (float) $invoice->total_taxes;

        $qb_invoice = $this->makeQbInvoice(automatic_taxes: true);
        $method = (new ReflectionClass($qb_invoice))->getMethod('validateAndSyncAmounts');
        $method->setAccessible(true);
        $method->invoke($qb_invoice, [
            'TotalAmt' => $original_amount,
            'TxnTaxDetail' => ['TotalTax' => $original_tax],
        ], $invoice);

        $invoice = $invoice->fresh();
        $this->assertEqualsWithDelta($original_amount, (float) $invoice->amount, 0.001);
        $this->assertEqualsWithDelta($original_tax, (float) $invoice->total_taxes, 0.001);
    }

    private function makeQbInvoice(bool $automatic_taxes): QbInvoice
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => [
                'automatic_taxes' => $automatic_taxes,
                'country' => 'US',
                'tax_rate_map' => [],
                'composite_tax_code_map' => [],
            ],
        ]);
        $this->company->save();

        return new QbInvoice(new QuickbooksService($this->company->fresh()));
    }

    private function makeSentInvoiceForAstBalanceTest(float $initial_balance, float $client_balance): Invoice
    {
        $invoice = $this->makeInvoiceWithLines([
            $this->lineItem('Widget', 100, tax_rate: 0),
        ]);
        $invoice->status_id = Invoice::STATUS_SENT;
        $invoice->amount = $initial_balance;
        $invoice->balance = $initial_balance;
        $invoice->saveQuietly();

        $this->client->balance = $client_balance;
        $this->client->saveQuietly();

        return $invoice->fresh(['client']);
    }

    private function makeInvoiceWithLines(array $line_items): Invoice
    {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'number' => 'AST-FIX-' . uniqid(),
            'date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => true,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ]);
        $invoice->line_items = $line_items;
        $invoice->saveQuietly();
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        return $invoice->fresh(['client']);
    }

    private function lineItem(string $name, float $cost, float $tax_rate = 0, string $tax_id = '1'): object
    {
        $line = InvoiceItemFactory::create();
        $line->product_key = $name;
        $line->notes = $name;
        $line->quantity = 1;
        $line->cost = $cost;
        $line->line_total = $cost;
        $line->tax_id = $tax_id;
        $line->tax_name1 = $tax_rate > 0 ? 'Existing' : '';
        $line->tax_rate1 = $tax_rate;

        return $line;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tax_lines
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $lines
     * @return array<string, mixed>
     */
    private function qbResponseWithTax(float $total_tax, array $tax_lines, array $lines, float $total_amt = 0): array
    {
        if ($total_amt <= 0) {
            $total_amt = 100 + $total_tax;
        }

        return [
            'TotalAmt' => $total_amt,
            'TxnTaxDetail' => [
                'TotalTax' => $total_tax,
                'TaxLine' => $tax_lines,
            ],
            'Line' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taxLine(float $amount, float $percent, float $net_taxable): array
    {
        return [
            'Amount' => $amount,
            'DetailType' => 'TaxLineDetail',
            'TaxLineDetail' => [
                'TaxPercent' => $percent,
                'NetAmountTaxable' => $net_taxable,
                'PercentBased' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesLine(string $tax_code): array
    {
        return [
            'DetailType' => 'SalesItemLineDetail',
            'Amount' => 100,
            'SalesItemLineDetail' => [
                'TaxCodeRef' => [
                    'value' => $tax_code,
                ],
            ],
        ];
    }

    private function invokeProcessTax(QbInvoice $qb_invoice, mixed $qb_response, Invoice $invoice): void
    {
        $method = (new ReflectionClass($qb_invoice))->getMethod('processQuickbooksTaxResponse');
        $method->setAccessible(true);
        $method->invoke($qb_invoice, $qb_response, $invoice);
    }
}
