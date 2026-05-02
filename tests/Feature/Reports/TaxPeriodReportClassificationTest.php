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
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use App\DataMapper\CompanySettings;
use App\Factory\InvoiceItemFactory;
use App\Services\Report\TaxPeriodReport;
use App\Services\Report\TaxPeriod\LineClassifier;
use App\Services\Report\TaxPeriod\TaxClassificationCalculator;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
