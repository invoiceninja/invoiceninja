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

namespace Tests\Unit\Chart;

use Tests\TestCase;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Tests\MockAccountData;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\Chart\ChartService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CashFlowForecastTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private Company $test_company;
    private Client $test_client;
    private Client $test_client_b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $settings = CompanySettings::defaults();
        $settings->currency_id = '1';
        $settings->country_id = '840';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;

        $this->test_company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $client_settings = ClientSettings::defaults();
        $client_settings->currency_id = '1';

        $this->test_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'settings' => $client_settings,
            'balance' => 0,
            'paid_to_date' => 0,
        ]);

        $this->test_client_b = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'settings' => $client_settings,
            'balance' => 0,
            'paid_to_date' => 0,
        ]);
    }

    private function getService(): ChartService
    {
        return new ChartService($this->test_company, $this->user, true);
    }

    private function createPaidInvoice(
        Client $client,
        float $amount,
        string $invoice_date,
        string $payment_date,
        ?string $due_date = null
    ): array {
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => $amount,
            'balance' => 0,
            'paid_to_date' => $amount,
            'status_id' => Invoice::STATUS_PAID,
            'date' => $invoice_date,
            'due_date' => $due_date ?? $invoice_date,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $payment = Payment::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => $amount,
            'applied' => $amount,
            'refunded' => 0,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => $payment_date,
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
        ]);

        $payment->invoices()->attach($invoice->id, [
            'amount' => $amount,
        ]);

        return ['invoice' => $invoice, 'payment' => $payment];
    }

    // ─── Outstanding Invoice Projection ─────────────────────────────

    public function testForecastProjectsOutstandingInvoicesIntoCorrectBucket(): void
    {
        // Build client history: avg_payment_days = 10
        $this->createPaidInvoice($this->test_client, 100, '2025-12-01', '2025-12-11', '2025-12-15');
        $this->createPaidInvoice($this->test_client, 100, '2025-12-10', '2025-12-20', '2025-12-25');

        // Create an outstanding invoice dated today
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500,
            'balance' => 500,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $start = now()->format('Y-m-d');
        $end = now()->addMonths(3)->format('Y-m-d');
        $result = $cs->cashflow_forecast($start, $end);

        $this->assertArrayHasKey('buckets', $result);
        $this->assertArrayHasKey('totals', $result);

        // The outstanding invoice should appear somewhere in the inflows
        $totalOutstandingAmount = 0;
        foreach ($result['buckets'] as $bucket) {
            $totalOutstandingAmount += $bucket['inflows']['outstanding_invoices']['amount'];
        }
        $this->assertEqualsWithDelta(500.0, $totalOutstandingAmount, 0.01);
    }

    public function testForecastUsesCompanyFallbackForNewClients(): void
    {
        // Build company-wide history on client A: avg 10 days
        $this->createPaidInvoice($this->test_client, 100, '2025-12-01', '2025-12-11', '2025-12-15');

        // Client B has NO payment history but has an outstanding invoice
        Invoice::factory()->create([
            'client_id' => $this->test_client_b->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 1000,
            'balance' => 1000,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d'));

        // Should still project the invoice (using company fallback)
        $totalOutstanding = 0;
        foreach ($result['buckets'] as $bucket) {
            $totalOutstanding += $bucket['inflows']['outstanding_invoices']['amount'];
        }
        $this->assertEqualsWithDelta(1000.0, $totalOutstanding, 0.01);
    }

    // ─── Recurring Invoice Projection ───────────────────────────────

    public function testForecastProjectsRecurringInvoicesForward(): void
    {
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addDays(7)->format('Y-m-d'),
            'remaining_cycles' => -1,
            'auto_bill_enabled' => false,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d'));

        $totalRecurring = 0;
        $recurringCount = 0;
        foreach ($result['buckets'] as $bucket) {
            $totalRecurring += $bucket['inflows']['recurring_invoices']['amount'];
            $recurringCount += $bucket['inflows']['recurring_invoices']['count'];
        }

        // Should project at least 3 occurrences over 3 months
        $this->assertGreaterThanOrEqual(3, $recurringCount);
        $this->assertEqualsWithDelta(200.0 * $recurringCount, $totalRecurring, 0.01);
    }

    public function testForecastRespectsRemainingCycles(): void
    {
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addDays(1)->format('Y-m-d'),
            'remaining_cycles' => 2,
            'auto_bill_enabled' => false,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(6)->format('Y-m-d'));

        $recurringCount = 0;
        foreach ($result['buckets'] as $bucket) {
            $recurringCount += $bucket['inflows']['recurring_invoices']['count'];
        }

        $this->assertEquals(2, $recurringCount);
    }

    public function testForecastWeightsAutoBillHigher(): void
    {
        // Auto-bill recurring invoice
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => 1,
            'auto_bill_enabled' => true,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Non-auto-bill recurring invoice
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client_b->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => 1,
            'auto_bill_enabled' => false,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(2)->format('Y-m-d'));

        // Both should have same raw amount, but auto-bill should have higher weighted
        $totalWeighted = 0;
        $totalRaw = 0;
        foreach ($result['buckets'] as $bucket) {
            $totalWeighted += $bucket['inflows']['recurring_invoices']['weighted_amount'];
            $totalRaw += $bucket['inflows']['recurring_invoices']['amount'];
        }

        // weighted = 100*0.95 + 100*0.75 = 170, raw = 200
        $this->assertEqualsWithDelta(200.0, $totalRaw, 0.01);
        $this->assertEqualsWithDelta(170.0, $totalWeighted, 0.01);
    }

    // ─── Recurring Expenses ─────────────────────────────────────────

    public function testForecastProjectsRecurringExpensesAsOutflows(): void
    {
        RecurringExpense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 150,
            'status_id' => 2, // active
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addDays(3)->format('Y-m-d'),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
            'uses_inclusive_taxes' => false,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d'));

        $totalExpenses = 0;
        foreach ($result['buckets'] as $bucket) {
            $totalExpenses += $bucket['outflows']['recurring_expenses']['amount'];
        }

        $this->assertGreaterThan(0, $totalExpenses);
    }

    // ─── One-Off Expenses ───────────────────────────────────────────

    public function testForecastIncludesOneOffExpenses(): void
    {
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500,
            'date' => now()->addDays(10)->format('Y-m-d'),
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
            'uses_inclusive_taxes' => false,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(2)->format('Y-m-d'));

        $totalOneOff = 0;
        foreach ($result['buckets'] as $bucket) {
            $totalOneOff += $bucket['outflows']['one_off_expenses']['amount'];
        }

        $this->assertEqualsWithDelta(500.0, $totalOneOff, 0.01);
    }

    // ─── Open Quotes ────────────────────────────────────────────────

    public function testForecastProjectsOpenQuotesWeightedByConversion(): void
    {
        // Create conversion history: 2 quotes, 1 converted = 50% rate
        $convertedInvoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100,
            'balance' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2025-11-15',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100,
            'status_id' => Quote::STATUS_CONVERTED,
            'date' => '2025-11-01',
            'invoice_id' => $convertedInvoice->id,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200,
            'status_id' => Quote::STATUS_SENT,
            'date' => '2025-11-05',
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Create an open quote to forecast
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 1000,
            'status_id' => Quote::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d'));

        $totalQuoteWeighted = 0;
        $totalQuoteRaw = 0;
        foreach ($result['buckets'] as $bucket) {
            $totalQuoteWeighted += $bucket['inflows']['quote_pipeline']['weighted_amount'];
            $totalQuoteRaw += $bucket['inflows']['quote_pipeline']['amount'];
        }

        // Two open quotes: $200 (historical) + $1000 (new) = $1200 raw
        // Conversion rate = 1/3 = 33.33%, weighted = 1200 * 0.3333 = ~400
        $this->assertEqualsWithDelta(1200.0, $totalQuoteRaw, 0.01);
        $this->assertEqualsWithDelta(400.0, $totalQuoteWeighted, 1.0);
    }

    // ─── Net Calculation ────────────────────────────────────────────

    public function testForecastNetCalculation(): void
    {
        // Recurring income
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 1000,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => 1,
            'auto_bill_enabled' => true,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // One-off expense
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 400,
            'date' => now()->addDays(5)->format('Y-m-d'),
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
            'uses_inclusive_taxes' => false,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(2)->format('Y-m-d'));

        $this->assertEqualsWithDelta(
            $result['totals']['total_inflows'] - $result['totals']['total_outflows'],
            $result['totals']['net'],
            0.01
        );
    }

    // ─── Weekly Buckets ─────────────────────────────────────────────

    public function testForecastWeeklyBuckets(): void
    {
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_WEEKLY,
            'next_send_date' => now()->addDays(1)->format('Y-m-d'),
            'remaining_cycles' => 4,
            'auto_bill_enabled' => true,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(2)->format('Y-m-d'), 'weekly');

        $this->assertEquals('weekly', $result['bucket_type']);

        // Weekly bucket keys should be like 2026-W14
        foreach ($result['buckets'] as $bucket) {
            $this->assertMatchesRegularExpression('/^\d{4}-W\d{2}$/', $bucket['period']);
        }
    }

    // ─── Empty Data ─────────────────────────────────────────────────

    public function testForecastEmptyData(): void
    {
        $cs = $this->getService();
        $result = $cs->cashflow_forecast(now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d'));

        $this->assertArrayHasKey('buckets', $result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertEquals(0, $result['totals']['total_inflows']);
        $this->assertEquals(0, $result['totals']['total_outflows']);
        $this->assertEquals(0, $result['totals']['net']);
    }

    // ─── API Endpoint ───────────────────────────────────────────────

    public function testForecastEndpoint(): void
    {
        $data = [
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(3)->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/charts/cashflow_forecast', $data);

        $response->assertStatus(200);

        $json = $response->json();
        $this->assertArrayHasKey('buckets', $json);
        $this->assertArrayHasKey('totals', $json);
        $this->assertArrayHasKey('bucket_type', $json);
        $this->assertEquals('monthly', $json['bucket_type']);
    }

    public function testForecastEndpointAcceptsWeeklyBucketType(): void
    {
        $data = [
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(2)->format('Y-m-d'),
            'bucket_type' => 'weekly',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/charts/cashflow_forecast', $data);

        $response->assertStatus(200);
        $this->assertEquals('weekly', $response->json('bucket_type'));
    }
}
