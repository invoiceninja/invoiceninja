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

namespace Tests\Feature\Reports;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Product;
use App\Utils\Traits\MakesHash;
use App\DataMapper\CompanySettings;
use App\Factory\InvoiceItemFactory;
use App\Services\Report\TaxPeriodReport;
use App\Services\Report\TaxPeriod\LineClassifier;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Report\TaxPeriod\TaxClassificationCalculator;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\App;
use App\Listeners\Invoice\InvoiceTransactionEventEntry;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;

/**
 * Validates Path B: persisted tax_details_by_classification on each
 * TransactionEvent and surfaced as a Type column in the Tax Period Report.
 */
class TaxPeriodReportClassificationTest extends TestCase
{
    use MakesHash;

    private Account $account;
    private User $user;
    private Company $company;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->buildData();
    }

    private function buildData(): void
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => \Illuminate\Support\Str::random(32) . '@example.com',
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->user->companies()->attach($this->company->id, [
            'account_id' => $this->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            'settings' => null,
        ]);

        $token = \Illuminate\Support\Str::random(64);
        $company_token = new \App\Models\CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test';
        $company_token->token = $token;
        $company_token->is_system = true;
        $company_token->save();

        $truth = app()->make(\App\Utils\TruthSource::class);
        $truth->setCompanyUser($this->user->company_users()->first());
        $truth->setCompanyToken($company_token);
        $truth->setUser($this->user);
        $truth->setCompany($this->company);

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'postal_code' => '12345',
        ]);
    }

    private function makeItem(array $overrides): object
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 0;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;

        foreach ($overrides as $k => $v) {
            $item->{$k} = $v;
        }

        return $item;
    }

    private function makeInvoice(array $line_items, array $invoice_overrides = []): Invoice
    {
        $invoice = Invoice::factory()->create(array_merge([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'line_items' => $line_items,
            'status_id' => Invoice::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => '', 'tax_rate1' => 0,
            'tax_name2' => '', 'tax_rate2' => 0,
            'tax_name3' => '', 'tax_rate3' => 0,
            'custom_surcharge1' => 0, 'custom_surcharge2' => 0,
            'custom_surcharge3' => 0, 'custom_surcharge4' => 0,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ], $invoice_overrides));

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->createInvitations()->save();

        return $invoice->fresh();
    }

    private function classificationRows(Invoice $invoice): array
    {
        $event = $invoice->transaction_events()->first();
        $rows = $event->metadata->tax_report->tax_details_by_classification ?? [];
        return is_array($rows) ? $rows : (array) $rows;
    }

    private function sumRows(array $rows, string $field): float
    {
        return round(array_sum(array_map(fn($r) => (float) ($r[$field] ?? 0), $rows)), 2);
    }

    private function sumSummaryColumn(array $summary_rows, string $header): float
    {
        $headers = $summary_rows[0] ?? [];
        $index = $this->indexFor($headers, $header);

        return round(array_sum(array_map(
            fn (array $row): float => (float) ($row[$index] ?? 0),
            array_slice($summary_rows, 1),
        )), 2);
    }

    private function summaryColumnValues(array $summary_rows, string $header): array
    {
        $headers = $summary_rows[0] ?? [];
        $index = $this->indexFor($headers, $header);

        return array_map(
            fn (array $row): mixed => $row[$index] ?? null,
            array_slice($summary_rows, 1),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<int, mixed>
     */
    private function rowMatchingColumns(array $rows, array $criteria): array
    {
        $headers = $rows[0] ?? [];

        foreach (array_slice($rows, 1) as $row) {
            foreach ($criteria as $header => $expected) {
                $index = $this->indexFor($headers, (string) $header);

                if ((string) ($row[$index] ?? '') !== (string) $expected) {
                    continue 2;
                }
            }

            return $row;
        }

        $this->fail('Expected row matching criteria: ' . json_encode($criteria) . ' in rows: ' . json_encode($rows));
    }

    private function overviewValue(array $overview_rows, string $metric): mixed
    {
        foreach (array_slice($overview_rows, 1) as $row) {
            if ((string) ($row[0] ?? '') === $metric) {
                return $row[1] ?? null;
            }
        }

        $this->fail('Expected filing overview metric: ' . $metric);
    }

    private function forceUnitedStatesCompany(): void
    {
        $settings = $this->company->settings;
        $settings->country_id = (string) Country::query()->where('iso_3166_2', 'US')->firstOrFail()->id;
        $this->company->settings = $settings;
        $this->company->save();
    }

    private function indexFor(array $headers, string $header): int
    {
        $index = array_search($header, $headers, true);
        $this->assertNotFalse($index, "Expected {$header} header to exist");

        return (int) $index;
    }

    /* -------------------- Tests -------------------- */

    public function testProductOnlyInvoiceHasAllProductRows(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
            $this->makeItem(['cost' => 200, 'line_total' => 200, 'type_id' => '1', 'tax_id' => '1']),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);
        $rows = $this->classificationRows($invoice->fresh());

        $this->assertNotEmpty($rows, 'tax_details_by_classification must be persisted');
        foreach ($rows as $row) {
            $this->assertSame(LineClassifier::PRODUCT, $row['classification']);
        }
        $this->assertEquals(30.0, $this->sumRows($rows, 'tax_amount'));
        $this->assertEquals(300.0, $this->sumRows($rows, 'taxable_amount'));

        $this->travelBack();
    }

    public function testMixedProductAndServiceSplitsAtSameRate(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
            $this->makeItem(['cost' => 200, 'line_total' => 200, 'type_id' => '2', 'tax_id' => '2']),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);
        $rows = $this->classificationRows($invoice->fresh());

        $by_class = collect($rows)->groupBy('classification');
        $this->assertTrue($by_class->has(LineClassifier::PRODUCT));
        $this->assertTrue($by_class->has(LineClassifier::SERVICE));

        $this->assertEquals(10.0, (float) $by_class[LineClassifier::PRODUCT][0]['tax_amount']);
        $this->assertEquals(100.0, (float) $by_class[LineClassifier::PRODUCT][0]['taxable_amount']);
        $this->assertEquals(20.0, (float) $by_class[LineClassifier::SERVICE][0]['tax_amount']);
        $this->assertEquals(200.0, (float) $by_class[LineClassifier::SERVICE][0]['taxable_amount']);

        $this->assertEquals(30.0, $this->sumRows($rows, 'tax_amount'));
        $this->assertEquals(300.0, $this->sumRows($rows, 'taxable_amount'));

        $this->travelBack();
    }

    public function testTaskLineIsLabor(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
            $this->makeItem(['cost' => 150, 'line_total' => 150, 'type_id' => '2', 'tax_id' => '2', 'task_id' => 'abc-task-id']),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);
        $rows = $this->classificationRows($invoice->fresh());

        $by_class = collect($rows)->groupBy('classification');
        $this->assertTrue($by_class->has(LineClassifier::LABOR), 'task_id line should be classified as labor');
        $this->assertEquals(15.0, (float) $by_class[LineClassifier::LABOR][0]['tax_amount']);
        $this->assertEquals(25.0, $this->sumRows($rows, 'tax_amount'));

        $this->travelBack();
    }

    public function testExpenseLineViaExpenseId(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100]),
            $this->makeItem(['cost' => 50, 'line_total' => 50, 'expense_id' => 'exp-123']),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);
        $rows = $this->classificationRows($invoice->fresh());

        $by_class = collect($rows)->groupBy('classification');
        $this->assertTrue($by_class->has(LineClassifier::EXPENSE));
        $this->assertEquals(5.0, (float) $by_class[LineClassifier::EXPENSE][0]['tax_amount']);

        $this->travelBack();
    }

    public function testInclusiveTaxMixedTypesTiesBackToAggregate(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice(
            [
                $this->makeItem(['cost' => 110, 'line_total' => 110, 'type_id' => '1', 'tax_id' => '1']),
                $this->makeItem(['cost' => 220, 'line_total' => 220, 'type_id' => '2', 'tax_id' => '2']),
            ],
            ['uses_inclusive_taxes' => true],
        );

        (new InvoiceTransactionEventEntry())->run($invoice);
        $event = $invoice->fresh()->transaction_events()->first();

        $rows = $this->classificationRows($invoice->fresh());
        $aggregate_tax = (float) $event->metadata->tax_report->tax_summary->tax_amount;

        $this->assertEqualsWithDelta($aggregate_tax, $this->sumRows($rows, 'tax_amount'), 0.02);
        $this->assertGreaterThan(0, $aggregate_tax);

        $by_class = collect($rows)->groupBy('classification');
        $this->assertTrue($by_class->has(LineClassifier::PRODUCT));
        $this->assertTrue($by_class->has(LineClassifier::SERVICE));

        $this->travelBack();
    }

    public function testMultipleTaxRatesProduceCartesianBuckets(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem([
                'cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1',
                'tax_name1' => 'GST', 'tax_rate1' => 10,
                'tax_name2' => 'PST', 'tax_rate2' => 5,
            ]),
            $this->makeItem([
                'cost' => 200, 'line_total' => 200, 'type_id' => '2', 'tax_id' => '2',
                'tax_name1' => 'GST', 'tax_rate1' => 10,
                'tax_name2' => 'PST', 'tax_rate2' => 5,
            ]),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);
        $rows = $this->classificationRows($invoice->fresh());

        $bucketed = [];
        foreach ($rows as $r) {
            $key = $r['tax_rate'] . '|' . $r['classification'];
            $bucketed[$key] = ($bucketed[$key] ?? 0) + (float) $r['tax_amount'];
        }

        $this->assertEqualsWithDelta(10.0, $bucketed['10|product'] ?? 0, 0.02);
        $this->assertEqualsWithDelta(5.0,  $bucketed['5|product']  ?? 0, 0.02);
        $this->assertEqualsWithDelta(20.0, $bucketed['10|service'] ?? 0, 0.02);
        $this->assertEqualsWithDelta(10.0, $bucketed['5|service']  ?? 0, 0.02);

        $this->travelBack();
    }

    public function testCashModePartialPaymentScalesByPaidRatio(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
            $this->makeItem(['cost' => 200, 'line_total' => 200, 'type_id' => '2', 'tax_id' => '2']),
        ]);

        $payment = \App\Models\Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'amount' => 165,
            'applied' => 165,
            'date' => now()->format('Y-m-d'),
        ]);

        $payment->invoices()->attach($invoice->id, ['amount' => 165, 'refunded' => 0]);
        $invoice->paid_to_date = 165;
        $invoice->balance = $invoice->amount - 165;
        $invoice->save();

        (new InvoiceTransactionEventEntryCash())->run($invoice->fresh(), '2025-10-01', '2025-10-31');

        $event = $invoice->fresh()->transaction_events()
            ->where('event_id', \App\Models\TransactionEvent::PAYMENT_CASH)
            ->first();

        $this->assertNotNull($event);
        $rows = $event->metadata->tax_report->tax_details_by_classification ?? [];
        $rows = is_array($rows) ? $rows : (array) $rows;

        $aggregate_tax = (float) $event->metadata->tax_report->tax_summary->tax_amount;
        $this->assertEqualsWithDelta(
            $aggregate_tax,
            $this->sumRows($rows, 'tax_amount'),
            0.02,
            'Per-classification rows must tie back to aggregate tax',
        );

        $this->assertEqualsWithDelta(15.0, $aggregate_tax, 0.02, 'Half-paid invoice should report half tax');

        $this->travelBack();
    }

    public function testBootResetsLocaleBeforeBuildingHeaders(): void
    {
        $this->assertSame('en', $this->company->locale());

        App::forgetInstance('translator');
        App::setLocale('de');
        $this->assertSame('Rechnungsnummer', ctrans('texts.invoice_number'));

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $data = $report->boot()->getData();

        $this->assertSame('en', App::getLocale());
        $this->assertSame('Invoice Number', $data['invoice_items'][0][0]);
        $this->assertContains('Filing Period', $data['summary'][0]);
    }

    public function testReportRendersTypeColumn(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
            $this->makeItem(['cost' => 200, 'line_total' => 200, 'type_id' => '2', 'tax_id' => '2']),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $data = $report->boot()->getData();

        $headers = $data['invoice_items'][0];
        $type_index = array_search(ctrans('texts.type'), $headers, true);
        $this->assertNotFalse($type_index, 'Invoice items sheet must have a Type column');

        $classifications = [];
        for ($i = 1; $i < count($data['invoice_items']); $i++) {
            $classifications[] = $data['invoice_items'][$i][$type_index];
        }

        $this->assertContains(LineClassifier::PRODUCT, $classifications);
        $this->assertContains(LineClassifier::SERVICE, $classifications);

        $this->travelBack();
    }

    public function testReportAddsReportingBucketAndSummaryFromClientFallback(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->shipping_state = null;
        $this->client->shipping_city = null;
        $this->client->shipping_postal_code = null;
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($invoice);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $data = $report->boot()->getData();
        $item_headers = $data['invoice_items'][0];
        $item_row = $data['invoice_items'][1];
        $bucket = $item_row[$this->indexFor($item_headers, ctrans('texts.reporting_bucket'))];

        $this->assertStringContainsString('US', $bucket);
        $this->assertStringContainsString('CA', $bucket);
        $this->assertStringContainsString('San Diego', $bucket);
        $this->assertStringContainsString('92101', $bucket);
        $this->assertStringContainsString(LineClassifier::PRODUCT, $bucket);

        $this->assertCount(2, $data['summary']);
        $summary_headers = $data['summary'][0];
        $summary_row = $data['summary'][1];

        $this->assertSame('2025-10', $summary_row[$this->indexFor($summary_headers, ctrans('texts.filing_period'))]);
        $this->assertSame('2025-10-01', $summary_row[$this->indexFor($summary_headers, ctrans('texts.period_start'))]);
        $this->assertSame('2025-10-31', $summary_row[$this->indexFor($summary_headers, ctrans('texts.period_end'))]);
        $this->assertSame($bucket, $summary_row[$this->indexFor($summary_headers, ctrans('texts.reporting_bucket'))]);
        $this->assertSame('CA', $summary_row[$this->indexFor($summary_headers, 'State')]);
        $this->assertSame('San Diego', $summary_row[$this->indexFor($summary_headers, 'City')]);
        $this->assertEquals(100.0, $summary_row[$this->indexFor($summary_headers, ctrans('texts.taxable_amount'))]);
        $this->assertEquals(10.0, $summary_row[$this->indexFor($summary_headers, ctrans('texts.tax_amount'))]);
        $this->assertEquals(1, $summary_row[$this->indexFor($summary_headers, ctrans('texts.invoice_count'))]);

        $monthly_row = $data['monthly_filing_summary'][1];
        $monthly_headers = $data['monthly_filing_summary'][0];
        $this->assertSame('2025-10', $monthly_row[$this->indexFor($monthly_headers, ctrans('texts.filing_period'))]);
        $this->assertEquals(100.0, $monthly_row[$this->indexFor($monthly_headers, ctrans('texts.gross_sales'))]);
        $this->assertEquals(100.0, $monthly_row[$this->indexFor($monthly_headers, ctrans('texts.taxable_amount'))]);
        $this->assertEquals(10.0, $monthly_row[$this->indexFor($monthly_headers, ctrans('texts.tax_amount'))]);

        $state_row = $data['state_breakdown'][1];
        $state_headers = $data['state_breakdown'][0];
        $this->assertSame('2025-10', $state_row[$this->indexFor($state_headers, ctrans('texts.filing_period'))]);
        $this->assertSame('CA', $state_row[$this->indexFor($state_headers, 'State')]);
        $this->assertEquals(100.0, $state_row[$this->indexFor($state_headers, ctrans('texts.gross_sales'))]);
        $this->assertEquals(10.0, $state_row[$this->indexFor($state_headers, ctrans('texts.tax_amount'))]);

        $this->travelBack();
    }

    public function testMonthlyFilingAndStateBreakdownAggregateAcrossPeriodsAndStates(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->shipping_state = null;
        $this->client->shipping_city = null;
        $this->client->shipping_postal_code = null;
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $california_invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($california_invoice, '2025-10-31');

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'state' => 'NY',
            'city' => 'New York',
            'postal_code' => '10001',
            'shipping_state' => null,
            'shipping_city' => null,
            'shipping_postal_code' => null,
        ]);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $new_york_invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 200, 'line_total' => 200, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($new_york_invoice, '2025-11-30');

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-11-30',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $data = $report->boot()->getData();
        $monthly_headers = $data['monthly_filing_summary'][0];
        $state_headers = $data['state_breakdown'][0];

        $october_row = $this->rowMatchingColumns($data['monthly_filing_summary'], [
            ctrans('texts.filing_period') => '2025-10',
            ctrans('texts.tax_treatment') => ctrans('texts.taxable_sales'),
        ]);
        $november_row = $this->rowMatchingColumns($data['monthly_filing_summary'], [
            ctrans('texts.filing_period') => '2025-11',
            ctrans('texts.tax_treatment') => ctrans('texts.taxable_sales'),
        ]);

        $this->assertSame('2025-10-01', $october_row[$this->indexFor($monthly_headers, ctrans('texts.period_start'))]);
        $this->assertSame('2025-10-31', $october_row[$this->indexFor($monthly_headers, ctrans('texts.period_end'))]);
        $this->assertEquals(100.0, $october_row[$this->indexFor($monthly_headers, ctrans('texts.gross_sales'))]);
        $this->assertEquals(10.0, $october_row[$this->indexFor($monthly_headers, ctrans('texts.tax_amount'))]);
        $this->assertEquals(200.0, $november_row[$this->indexFor($monthly_headers, ctrans('texts.gross_sales'))]);
        $this->assertEquals(20.0, $november_row[$this->indexFor($monthly_headers, ctrans('texts.tax_amount'))]);

        $california_row = $this->rowMatchingColumns($data['state_breakdown'], [
            ctrans('texts.filing_period') => '2025-10',
            'State' => 'CA',
        ]);
        $new_york_row = $this->rowMatchingColumns($data['state_breakdown'], [
            ctrans('texts.filing_period') => '2025-11',
            'State' => 'NY',
        ]);

        $this->assertSame(ctrans('texts.client_billing_source'), $california_row[$this->indexFor($state_headers, ctrans('texts.jurisdiction_source'))]);
        $this->assertEquals(100.0, $california_row[$this->indexFor($state_headers, ctrans('texts.gross_sales'))]);
        $this->assertEquals(10.0, $california_row[$this->indexFor($state_headers, ctrans('texts.tax_amount'))]);
        $this->assertSame(ctrans('texts.client_billing_source'), $new_york_row[$this->indexFor($state_headers, ctrans('texts.jurisdiction_source'))]);
        $this->assertEquals(200.0, $new_york_row[$this->indexFor($state_headers, ctrans('texts.gross_sales'))]);
        $this->assertEquals(20.0, $new_york_row[$this->indexFor($state_headers, ctrans('texts.tax_amount'))]);

        $jurisdiction_headers = $data['jurisdiction_breakdown'][0];
        $california_jurisdiction_row = $this->rowMatchingColumns($data['jurisdiction_breakdown'], [
            ctrans('texts.filing_period') => '2025-10',
            'State' => 'CA',
            'City' => 'San Diego',
            ctrans('texts.postal_code') => '92101',
            ctrans('texts.jurisdiction_source') => ctrans('texts.client_billing_source'),
        ]);
        $new_york_jurisdiction_row = $this->rowMatchingColumns($data['jurisdiction_breakdown'], [
            ctrans('texts.filing_period') => '2025-11',
            'State' => 'NY',
            'City' => 'New York',
            ctrans('texts.postal_code') => '10001',
            ctrans('texts.jurisdiction_source') => ctrans('texts.client_billing_source'),
        ]);

        $this->assertEquals(100.0, $california_jurisdiction_row[$this->indexFor($jurisdiction_headers, ctrans('texts.gross_sales'))]);
        $this->assertEquals(10.0, $california_jurisdiction_row[$this->indexFor($jurisdiction_headers, ctrans('texts.tax_amount'))]);
        $this->assertEquals(200.0, $new_york_jurisdiction_row[$this->indexFor($jurisdiction_headers, ctrans('texts.gross_sales'))]);
        $this->assertEquals(20.0, $new_york_jurisdiction_row[$this->indexFor($jurisdiction_headers, ctrans('texts.tax_amount'))]);

        $overview = $data['filing_overview'];
        $this->assertSame('2025-10-01 - 2025-11-30', $this->overviewValue($overview, ctrans('texts.report_period')));
        $this->assertEquals(300.0, $this->overviewValue($overview, ctrans('texts.gross_sales')));
        $this->assertEquals(300.0, $this->overviewValue($overview, ctrans('texts.taxable_amount')));
        $this->assertEquals(30.0, $this->overviewValue($overview, ctrans('texts.tax_amount')));
        $this->assertSame(2, $this->overviewValue($overview, ctrans('texts.current_filing_rows')));
        $this->assertSame(2, $this->overviewValue($overview, ctrans('texts.states_reported')));
        $this->assertSame(2, $this->overviewValue($overview, ctrans('texts.jurisdictions_reported')));
        $this->assertSame(2, $this->overviewValue($overview, ctrans('texts.rows_using_client_fallback')));

        $workbook_company = $this->company->fresh();
        $workbook_company->db = config('database.default');
        $xlsx_report = new TaxPeriodReport($workbook_company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-11-30',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $temp_path = tempnam(sys_get_temp_dir(), 'tax_period_report_');
        $spreadsheet = null;

        try {
            file_put_contents($temp_path, $xlsx_report->run());
            $spreadsheet = IOFactory::load($temp_path);
            $sheet_names = $spreadsheet->getSheetNames();

            $this->assertSame(ctrans('texts.filing_overview'), $sheet_names[0]);
            $this->assertContains(ctrans('texts.tax_summary'), $sheet_names);
            $this->assertContains(ctrans('texts.monthly_filing_summary'), $sheet_names);
            $this->assertContains(ctrans('texts.state_breakdown'), $sheet_names);
            $this->assertContains(ctrans('texts.jurisdiction_breakdown'), $sheet_names);
            $this->assertContains(ctrans('texts.corrections_review'), $sheet_names);

            $overview_sheet = $spreadsheet->getSheetByName(ctrans('texts.filing_overview'));
            $jurisdiction_sheet = $spreadsheet->getSheetByName(ctrans('texts.jurisdiction_breakdown'));

            $this->assertNotNull($overview_sheet);
            $this->assertNotNull($jurisdiction_sheet);
            $this->assertSame(ctrans('texts.report_period'), $overview_sheet->getCell('A2')->getValue());
            $this->assertSame('2025-10-01 - 2025-11-30', $overview_sheet->getCell('B2')->getValue());
            $this->assertSame(ctrans('texts.jurisdiction_source'), $jurisdiction_sheet->getCell('I1')->getValue());
        } finally {
            if ($spreadsheet !== null) {
                $spreadsheet->disconnectWorksheets();
            }

            if (is_string($temp_path) && file_exists($temp_path)) {
                unlink($temp_path);
            }
        }

        $this->travelBack();
    }

    public function testCashReportingBucketPreservesClassificationAfterPaymentUnwrap(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->shipping_state = null;
        $this->client->shipping_city = null;
        $this->client->shipping_postal_code = null;
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
        ], ['tax_data' => null]);

        $invoice = $invoice->fresh();
        $invoice->service()->applyPaymentAmount(110, 'p1')->save();
        $invoice = $invoice->fresh();

        (new InvoiceTransactionEventEntryCash())->run($invoice, '2025-10-01', '2025-10-31');

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => false,
        ], skip_initialization: true);

        $data = $report->boot()->getData();
        $item_headers = $data['invoice_items'][0];
        $item_row = $data['invoice_items'][1];

        $this->assertSame(LineClassifier::PRODUCT, $item_row[$this->indexFor($item_headers, ctrans('texts.type'))]);

        $bucket = $item_row[$this->indexFor($item_headers, ctrans('texts.reporting_bucket'))];
        $this->assertStringContainsString(LineClassifier::PRODUCT, $bucket);
        $this->assertStringNotContainsString(ctrans('texts.unknown'), $bucket);

        $summary_headers = $data['summary'][0];
        $summary_row = $data['summary'][1];
        $this->assertSame($bucket, $summary_row[$this->indexFor($summary_headers, ctrans('texts.reporting_bucket'))]);
        $this->assertEquals(100.0, $summary_row[$this->indexFor($summary_headers, ctrans('texts.taxable_amount'))]);
        $this->assertEquals(10.0, $summary_row[$this->indexFor($summary_headers, ctrans('texts.tax_amount'))]);

        $this->travelBack();
    }

    public function testSummaryUsesSalesBreakdownForExemptAndNonTaxableSales(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->shipping_state = null;
        $this->client->shipping_city = null;
        $this->client->shipping_postal_code = null;
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
            $this->makeItem([
                'cost' => 50,
                'line_total' => 50,
                'type_id' => '1',
                'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
                'tax_name1' => '',
                'tax_rate1' => 0,
            ]),
            $this->makeItem([
                'cost' => 30,
                'line_total' => 30,
                'type_id' => '2',
                'tax_id' => (string) Product::PRODUCT_TYPE_SERVICE,
                'tax_name1' => '',
                'tax_rate1' => 0,
            ]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($invoice);

        $event = $invoice->fresh()->transaction_events()->first();
        $this->assertNotEmpty($event->metadata->tax_report->sales_breakdown ?? []);

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $summary = $report->boot()->getData()['summary'];

        $this->assertEquals(180.0, $this->sumSummaryColumn($summary, ctrans('texts.gross_sales')));
        $this->assertEquals(100.0, $this->sumSummaryColumn($summary, ctrans('texts.taxable_amount')));
        $this->assertEquals(50.0, $this->sumSummaryColumn($summary, ctrans('texts.exempt_sales')));
        $this->assertEquals(30.0, $this->sumSummaryColumn($summary, ctrans('texts.non_taxable_sales')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.zero_rated_sales')));
        $this->assertEquals(10.0, $this->sumSummaryColumn($summary, ctrans('texts.tax_amount')));
        $this->assertContains(ctrans('texts.exempt_sales'), $this->summaryColumnValues($summary, ctrans('texts.tax_treatment')));
        $this->assertContains(ctrans('texts.non_taxable_sales'), $this->summaryColumnValues($summary, ctrans('texts.tax_treatment')));

        $this->travelBack();
    }

    public function testSummaryUsesSalesBreakdownDeltaForInvoiceCorrections(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->shipping_state = null;
        $this->client->shipping_city = null;
        $this->client->shipping_postal_code = null;
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
            $this->makeItem([
                'cost' => 50,
                'line_total' => 50,
                'type_id' => '1',
                'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
                'tax_name1' => '',
                'tax_rate1' => 0,
            ]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($invoice, '2025-10-31');

        $invoice = $invoice->fresh();
        $invoice->line_items = [
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
            $this->makeItem([
                'cost' => 70,
                'line_total' => 70,
                'type_id' => '1',
                'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
                'tax_name1' => '',
                'tax_rate1' => 0,
            ]),
        ];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());
        (new InvoiceTransactionEventEntry())->run($invoice->fresh(), '2025-11-30');

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-30',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $data = $report->boot()->getData();
        $summary = $data['summary'];
        $corrections = $data['corrections'];

        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.gross_sales')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.taxable_amount')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.exempt_sales')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.tax_amount')));

        $this->assertEquals(20.0, $this->sumSummaryColumn($corrections, ctrans('texts.gross_sales')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($corrections, ctrans('texts.taxable_amount')));
        $this->assertEquals(20.0, $this->sumSummaryColumn($corrections, ctrans('texts.exempt_sales')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($corrections, ctrans('texts.tax_amount')));
        $this->assertContains('delta', $this->summaryColumnValues($corrections, ctrans('texts.activity')));
        $this->assertContains(ctrans('texts.invoice_correction'), $this->summaryColumnValues($corrections, ctrans('texts.correction_type')));
        $this->assertContains(ctrans('texts.yes'), $this->summaryColumnValues($corrections, ctrans('texts.requires_review')));
        $this->assertContains('2025-10-31', $this->summaryColumnValues($corrections, ctrans('texts.original_tax_period')));
        $this->assertContains('2025-11-30', $this->summaryColumnValues($corrections, ctrans('texts.correction_recorded_period')));

        $this->travelBack();
    }

    public function testSamePeriodDeletedInvoiceAdjustmentStaysInCurrentSummary(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 1, 5)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($invoice, '2026-01-31');

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 1, 20)->startOfDay());

        $invoice = $invoice->fresh();
        $invoice->is_deleted = true;
        $invoice->save();

        (new InvoiceTransactionEventEntry())->run($invoice->fresh(), '2026-01-31');

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 2, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $data = $report->boot()->getData();
        $summary = $data['summary'];
        $corrections = $data['corrections'];

        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.gross_sales')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.taxable_amount')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.tax_amount')));
        $this->assertContains('updated', $this->summaryColumnValues($summary, ctrans('texts.activity')));
        $this->assertContains('deleted', $this->summaryColumnValues($summary, ctrans('texts.activity')));
        $this->assertCount(1, $corrections);

        $this->travelBack();
    }

    public function testPriorPeriodDeletedInvoiceAdjustmentIsReportedForCorrectionReviewOnly(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 15)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($invoice, '2025-12-31');

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 1, 15)->startOfDay());

        $invoice = $invoice->fresh();
        $invoice->is_deleted = true;
        $invoice->save();

        (new InvoiceTransactionEventEntry())->run($invoice->fresh(), '2026-01-31');

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 2, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $data = $report->boot()->getData();
        $summary = $data['summary'];
        $corrections = $data['corrections'];

        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.gross_sales')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.taxable_amount')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.tax_amount')));

        $this->assertEquals(-100.0, $this->sumSummaryColumn($corrections, ctrans('texts.gross_sales')));
        $this->assertEquals(-100.0, $this->sumSummaryColumn($corrections, ctrans('texts.taxable_amount')));
        $this->assertEquals(-10.0, $this->sumSummaryColumn($corrections, ctrans('texts.tax_amount')));
        $this->assertContains('deleted', $this->summaryColumnValues($corrections, ctrans('texts.activity')));
        $this->assertContains(ctrans('texts.status_correction'), $this->summaryColumnValues($corrections, ctrans('texts.correction_type')));
        $this->assertContains(ctrans('texts.yes'), $this->summaryColumnValues($corrections, ctrans('texts.requires_review')));
        $this->assertContains('2025-12-31', $this->summaryColumnValues($corrections, ctrans('texts.original_tax_period')));
        $this->assertContains('2026-01-31', $this->summaryColumnValues($corrections, ctrans('texts.correction_recorded_period')));

        $this->travelBack();
    }

    public function testPriorPeriodCashRefundIsReportedForCorrectionReviewOnly(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 12, 15)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
        ], ['tax_data' => null]);

        $invoice->paid_to_date = $invoice->amount;
        $invoice->balance = 0;
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->save();

        \App\Models\TransactionEvent::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'payment_id' => 0,
            'credit_id' => 0,
            'client_balance' => $invoice->client->balance,
            'client_paid_to_date' => $invoice->client->paid_to_date,
            'client_credit_balance' => $invoice->client->credit_balance,
            'invoice_balance' => $invoice->balance,
            'invoice_amount' => $invoice->amount,
            'invoice_partial' => $invoice->partial ?? 0,
            'invoice_paid_to_date' => $invoice->paid_to_date,
            'invoice_status' => $invoice->status_id,
            'payment_amount' => $invoice->amount,
            'payment_applied' => $invoice->amount,
            'payment_refunded' => $invoice->amount,
            'payment_status' => null,
            'event_id' => \App\Models\TransactionEvent::PAYMENT_REFUNDED,
            'timestamp' => \Carbon\Carbon::createFromDate(2026, 1, 15)->startOfDay()->timestamp,
            'metadata' => new \App\DataMapper\TransactionEventMetadata([
                'tax_report' => [
                    'tax_details' => [
                        [
                            'tax_name' => 'GST',
                            'tax_rate' => 10,
                            'taxable_amount' => -100,
                            'tax_amount' => -10,
                            'line_total' => 100,
                            'total_tax' => 10,
                            'postal_code' => '92101',
                        ],
                    ],
                    'sales_breakdown' => \App\Services\Report\TaxPeriod\SalesBreakdownCalculator::calculate($invoice->fresh(), -1),
                    'payment_history' => [
                        [
                            'number' => 'PAY-1',
                            'amount' => $invoice->amount,
                            'refunded' => $invoice->amount,
                            'date' => '2025-12-15',
                        ],
                    ],
                    'tax_summary' => [
                        'tax_amount' => -10,
                        'taxable_amount' => -100,
                        'status' => 'adjustment',
                    ],
                ],
            ]),
            'period' => '2026-01-31',
        ]);

        $this->travelTo(\Carbon\Carbon::createFromDate(2026, 2, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_income_billed' => false,
        ], skip_initialization: true);

        $data = $report->boot()->getData();
        $summary = $data['summary'];
        $corrections = $data['corrections'];

        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.gross_sales')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.taxable_amount')));
        $this->assertEquals(0.0, $this->sumSummaryColumn($summary, ctrans('texts.tax_amount')));

        $this->assertEquals(-100.0, $this->sumSummaryColumn($corrections, ctrans('texts.gross_sales')));
        $this->assertEquals(-100.0, $this->sumSummaryColumn($corrections, ctrans('texts.taxable_amount')));
        $this->assertEquals(-10.0, $this->sumSummaryColumn($corrections, ctrans('texts.tax_amount')));
        $this->assertContains(ctrans('texts.payment_correction'), $this->summaryColumnValues($corrections, ctrans('texts.correction_type')));
        $this->assertContains(ctrans('texts.yes'), $this->summaryColumnValues($corrections, ctrans('texts.requires_review')));
        $this->assertContains('2025-12-31', $this->summaryColumnValues($corrections, ctrans('texts.original_tax_period')));
        $this->assertContains('2026-01-31', $this->summaryColumnValues($corrections, ctrans('texts.correction_recorded_period')));

        $this->travelBack();
    }

    public function testSummaryMarksLegacyRowsWhenSalesBreakdownIsMissing(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->shipping_state = null;
        $this->client->shipping_city = null;
        $this->client->shipping_postal_code = null;
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($invoice, '2025-10-31');

        $event = $invoice->fresh()->transaction_events()->first();
        $metadata = $event->metadata;
        $metadata->tax_report->sales_breakdown = null;
        $event->metadata = $metadata;
        $event->saveQuietly();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $summary = $report->boot()->getData()['summary'];

        $this->assertContains(
            ctrans('texts.legacy_tax_detail_source'),
            $this->summaryColumnValues($summary, ctrans('texts.summary_source')),
        );
    }

    public function testInitializationBackfillsSalesBreakdownForSafeLegacyEvent(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 31)->setTime(12, 0));

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
            $this->makeItem([
                'cost' => 50,
                'line_total' => 50,
                'type_id' => '1',
                'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
                'tax_name1' => '',
                'tax_rate1' => 0,
            ]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($invoice, '2025-10-31');

        $event = $invoice->fresh()->transaction_events()->first();
        $metadata = $event->metadata;
        $metadata->tax_report->sales_breakdown = null;
        $event->metadata = $metadata;
        $event->saveQuietly();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: false);

        $summary = $report->boot()->getData()['summary'];
        $refreshed = \App\Models\TransactionEvent::query()->find($event->id);

        $this->assertNotEmpty($refreshed->metadata->tax_report->sales_breakdown ?? []);
        $this->assertContains(
            ctrans('texts.sales_breakdown_source'),
            $this->summaryColumnValues($summary, ctrans('texts.summary_source')),
        );
        $this->assertEquals(50.0, $this->sumSummaryColumn($summary, ctrans('texts.exempt_sales')));
    }

    public function testInitializationSkipsSalesBreakdownBackfillWhenInvoiceChangedAfterEvent(): void
    {
        $this->forceUnitedStatesCompany();
        $this->client->state = 'CA';
        $this->client->city = 'San Diego';
        $this->client->postal_code = '92101';
        $this->client->shipping_state = null;
        $this->client->shipping_city = null;
        $this->client->shipping_postal_code = null;
        $this->client->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
        ], ['tax_data' => null]);

        (new InvoiceTransactionEventEntry())->run($invoice, '2025-10-31');

        $event = $invoice->fresh()->transaction_events()->first();
        $metadata = $event->metadata;
        $metadata->tax_report->sales_breakdown = null;
        $event->metadata = $metadata;
        $event->saveQuietly();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 2)->startOfDay());

        $invoice = $invoice->fresh();
        $invoice->line_items = [
            $this->makeItem(['cost' => 150, 'line_total' => 150, 'type_id' => '1', 'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL]),
        ];
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: false);

        $summary = $report->boot()->getData()['summary'];
        $refreshed = \App\Models\TransactionEvent::query()->find($event->id);

        $this->assertEmpty($refreshed->metadata->tax_report->sales_breakdown ?? []);
        $this->assertContains(
            ctrans('texts.legacy_tax_detail_source'),
            $this->summaryColumnValues($summary, ctrans('texts.summary_source')),
        );
    }
    public function testFallbackForLegacyEventsWithoutClassification(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100]),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);

        $event = $invoice->fresh()->transaction_events()->first();
        $metadata = $event->metadata;
        $metadata->tax_report->tax_details_by_classification = null;
        $event->metadata = $metadata;
        $event->saveQuietly();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: true);

        $data = $report->boot()->getData();

        $this->assertGreaterThanOrEqual(2, count($data['invoice_items']),
            'Report must render rows from legacy tax_details when by_classification is absent');

        $this->travelBack();
    }

    public function testTransactionEventInvoiceRelationResolves(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);

        $event = \App\Models\TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertNotNull($event->invoice, 'invoice() relation must resolve');
        $this->assertSame($invoice->id, $event->invoice->id);

        $this->travelBack();
    }

    public function testTransactionEventInvoiceRelationResolvesForArchivedInvoice(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);

        $invoice->delete(); // soft delete == archived

        $event = \App\Models\TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->with('invoice')
            ->first();

        $this->assertNotNull($event->invoice, 'invoice() must withTrashed() so archived invoices still resolve');
        $this->assertSame($invoice->id, $event->invoice->id);

        $this->travelBack();
    }

    public function testBackfillRepopulatesMissingClassificationBreakdown(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 100, 'line_total' => 100, 'type_id' => '1', 'tax_id' => '1']),
            $this->makeItem(['cost' => 200, 'line_total' => 200, 'type_id' => '2', 'tax_id' => '2']),
        ]);

        (new InvoiceTransactionEventEntry())->run($invoice);

        // Simulate a legacy event that predates the by-classification breakdown.
        $event = $invoice->fresh()->transaction_events()->first();
        $metadata = $event->metadata;
        $metadata->tax_report->tax_details_by_classification = null;
        $event->metadata = $metadata;
        $event->saveQuietly();

        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 11, 1)->startOfDay());

        // skip_initialization:false runs backfillClassificationBreakdown(), which
        // walks each event via the TransactionEvent::invoice() relation.
        $report = new TaxPeriodReport($this->company, [
            'date_range' => 'custom',
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-31',
            'is_income_billed' => true,
        ], skip_initialization: false);

        $report->boot();

        $refreshed = \App\Models\TransactionEvent::query()->find($event->id);
        $breakdown = $refreshed->metadata->tax_report->tax_details_by_classification ?? null;

        $this->assertNotNull($breakdown, 'backfill must repopulate tax_details_by_classification');
        $this->assertNotEmpty((array) $breakdown);
        $this->assertEqualsWithDelta(30.0, $this->sumRows((array) $breakdown, 'tax_amount'), 0.02);

        $this->travelBack();
    }

    public function testCalculatorReconciliationTiesBackToAggregate(): void
    {
        $this->travelTo(\Carbon\Carbon::createFromDate(2025, 10, 1)->startOfDay());

        $invoice = $this->makeInvoice([
            $this->makeItem(['cost' => 33.33, 'line_total' => 33.33, 'type_id' => '1', 'tax_id' => '1']),
            $this->makeItem(['cost' => 66.67, 'line_total' => 66.67, 'type_id' => '2', 'tax_id' => '2']),
            $this->makeItem(['cost' => 11.11, 'line_total' => 11.11, 'type_id' => '1', 'tax_id' => '3']),
        ]);

        $aggregate = [[
            'tax_name' => 'GST 10%',
            'tax_rate' => 10.0,
            'taxable_amount' => 111.11,
            'tax_amount' => 11.11,
            'postal_code' => '12345',
        ]];

        $rows = TaxClassificationCalculator::calculate($invoice, 1.0, $aggregate);

        $this->assertEqualsWithDelta(11.11, $this->sumRows($rows, 'tax_amount'), 0.001);
        $this->assertEqualsWithDelta(111.11, $this->sumRows($rows, 'taxable_amount'), 0.001);

        $this->travelBack();
    }
}
