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

/**
 * Tests for App\Services\Chart\AnalyticsQueries
 */
class AnalyticsQueriesTest extends TestCase
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

    /**
     * Helper: create an invoice, a completed payment, and link them via paymentables.
     */
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

    // ─── getClientPaymentDelays ─────────────────────────────────────

    public function testClientPaymentDelaysReturnsCorrectDays(): void
    {
        // Invoice issued Jan 1, paid Jan 11 = 10 days
        $this->createPaidInvoice(
            $this->test_client,
            100.00,
            '2026-01-01',
            '2026-01-11',
            '2026-01-15'
        );

        // Invoice issued Jan 10, paid Jan 25 = 15 days
        $this->createPaidInvoice(
            $this->test_client,
            200.00,
            '2026-01-10',
            '2026-01-25',
            '2026-01-20'
        );

        $cs = $this->getService();
        $results = $cs->getClientPaymentDelays($this->test_client->id);

        $this->assertCount(2, $results);

        $delays = collect($results)->sortBy('invoice_date')->values();

        $this->assertEquals(10, $delays[0]->payment_days);
        $this->assertEquals(15, $delays[1]->payment_days);
        $this->assertEquals($this->test_client->id, $delays[0]->client_id);
    }

    public function testClientPaymentDelaysExcludesDeletedInvoices(): void
    {
        $this->createPaidInvoice(
            $this->test_client,
            100.00,
            '2026-01-01',
            '2026-01-11'
        );

        // Create a deleted invoice with payment
        $invoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 50.00,
            'balance' => 0,
            'paid_to_date' => 50.00,
            'status_id' => Invoice::STATUS_PAID,
            'date' => '2026-02-01',
            'due_date' => '2026-02-15',
            'is_deleted' => true,
            'exchange_rate' => 1,
        ]);

        $payment = Payment::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 50.00,
            'applied' => 50.00,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => '2026-02-10',
            'is_deleted' => false,
            'currency_id' => 1,
        ]);

        $payment->invoices()->attach($invoice->id, ['amount' => 50.00]);

        $cs = $this->getService();
        $results = $cs->getClientPaymentDelays($this->test_client->id);

        $this->assertCount(1, $results);
    }

    public function testClientPaymentDelaysExcludesUnpaidInvoices(): void
    {
        $this->createPaidInvoice(
            $this->test_client,
            100.00,
            '2026-01-01',
            '2026-01-11'
        );

        // Unpaid invoice (status SENT, no payment)
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'balance' => 200.00,
            'paid_to_date' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-02-01',
            'due_date' => '2026-02-15',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getClientPaymentDelays($this->test_client->id);

        $this->assertCount(1, $results);
    }

    public function testClientPaymentDelaysReturnsAllClientsWhenNoFilter(): void
    {
        $this->createPaidInvoice($this->test_client, 100.00, '2026-01-01', '2026-01-11');
        $this->createPaidInvoice($this->test_client_b, 200.00, '2026-01-05', '2026-01-20');

        $cs = $this->getService();
        $results = $cs->getClientPaymentDelays(null);

        $client_ids = collect($results)->pluck('client_id')->unique()->values()->all();

        $this->assertContains($this->test_client->id, $client_ids);
        $this->assertContains($this->test_client_b->id, $client_ids);
    }

    // ─── getClientPaymentSummary ────────────────────────────────────

    public function testClientPaymentSummaryComputesAverageAndLateRatio(): void
    {
        // Invoice 1: due Jan 15, paid Jan 11 (on time), payment_days = 10
        $this->createPaidInvoice(
            $this->test_client,
            100.00,
            '2026-01-01',
            '2026-01-11',
            '2026-01-15'
        );

        // Invoice 2: due Jan 20, paid Jan 25 (5 days late), payment_days = 15
        $this->createPaidInvoice(
            $this->test_client,
            200.00,
            '2026-01-10',
            '2026-01-25',
            '2026-01-20'
        );

        // Invoice 3: due Feb 20, paid Feb 10 (on time), payment_days = 9
        $this->createPaidInvoice(
            $this->test_client,
            150.00,
            '2026-02-01',
            '2026-02-10',
            '2026-02-20'
        );

        $cs = $this->getService();
        $results = $cs->getClientPaymentSummary($this->test_client->id);

        $this->assertCount(1, $results);

        $summary = $results[0];
        $this->assertEquals($this->test_client->id, $summary->client_id);
        $this->assertEquals(3, $summary->total_invoices);
        $this->assertEquals(1, $summary->late_invoices);

        // avg_payment_days = (10 + 15 + 9) / 3 = 11.33
        $this->assertEqualsWithDelta(11.33, $summary->avg_payment_days, 0.01);

        // late ratio = 1/3 = 0.3333
        $this->assertEqualsWithDelta(0.3333, $summary->late_payment_ratio, 0.001);

        // stddev should be > 0 (there is variance)
        $this->assertGreaterThan(0, $summary->stddev_payment_days);
    }

    public function testClientPaymentSummaryReturnsMultipleClients(): void
    {
        $this->createPaidInvoice($this->test_client, 100.00, '2026-01-01', '2026-01-06');
        $this->createPaidInvoice($this->test_client_b, 200.00, '2026-01-01', '2026-01-21');

        $cs = $this->getService();
        $results = $cs->getClientPaymentSummary(null);

        $client_ids = collect($results)->pluck('client_id')->all();

        $this->assertContains($this->test_client->id, $client_ids);
        $this->assertContains($this->test_client_b->id, $client_ids);

        $client_a = collect($results)->firstWhere('client_id', $this->test_client->id);
        $client_b = collect($results)->firstWhere('client_id', $this->test_client_b->id);

        $this->assertEquals(5, $client_a->avg_payment_days);
        $this->assertEquals(20, $client_b->avg_payment_days);
    }

    // ─── getCompanyPaymentSummary ───────────────────────────────────

    public function testCompanyPaymentSummaryAggregatesAllClients(): void
    {
        // Client A: 5 day payment delay
        $this->createPaidInvoice($this->test_client, 100.00, '2026-01-01', '2026-01-06', '2026-01-10');
        // Client B: 20 day payment delay (late, due_date is Jan 10)
        $this->createPaidInvoice($this->test_client_b, 200.00, '2026-01-01', '2026-01-21', '2026-01-10');

        $cs = $this->getService();
        $results = $cs->getCompanyPaymentSummary();

        $this->assertCount(1, $results);

        $summary = $results[0];

        // avg = (5 + 20) / 2 = 12.5
        $this->assertEqualsWithDelta(12.5, $summary->avg_payment_days, 0.01);
        $this->assertEquals(2, $summary->total_invoices);
        $this->assertEquals(1, $summary->late_invoices);
        $this->assertEqualsWithDelta(0.5, $summary->late_payment_ratio, 0.001);
    }

    public function testCompanyPaymentSummaryReturnsEmptyWhenNoData(): void
    {
        $cs = $this->getService();
        $results = $cs->getCompanyPaymentSummary();

        // Should return empty or a row with nulls
        if (count($results) > 0) {
            $this->assertNull($results[0]->avg_payment_days);
        } else {
            $this->assertCount(0, $results);
        }
    }

    // ─── getOutstandingInvoicesForForecasting ────────────────────────

    public function testOutstandingInvoicesReturnsSentAndPartialOnly(): void
    {
        // Create historical paid invoice so client has analytics data
        $this->createPaidInvoice($this->test_client, 100.00, '2025-12-01', '2025-12-10', '2025-12-15');

        // Sent invoice (should be returned)
        $sent = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'balance' => 500.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Partial invoice (should be returned)
        $partial = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300.00,
            'balance' => 150.00,
            'paid_to_date' => 150.00,
            'status_id' => Invoice::STATUS_PARTIAL,
            'date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Paid invoice (should NOT be returned)
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'balance' => 0,
            'paid_to_date' => 200.00,
            'status_id' => Invoice::STATUS_PAID,
            'date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Draft invoice (should NOT be returned)
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'balance' => 100.00,
            'status_id' => Invoice::STATUS_DRAFT,
            'date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getOutstandingInvoicesForForecasting();

        $invoice_ids = collect($results)->pluck('invoice_id')->all();

        $this->assertContains($sent->id, $invoice_ids);
        $this->assertContains($partial->id, $invoice_ids);
        $this->assertCount(2, $results);
    }

    public function testOutstandingInvoicesIncludeClientAnalytics(): void
    {
        // Create historical paid invoices to build client stats
        $this->createPaidInvoice($this->test_client, 100.00, '2025-11-01', '2025-11-11', '2025-11-15');
        $this->createPaidInvoice($this->test_client, 200.00, '2025-12-01', '2025-12-11', '2025-12-15');

        // Outstanding invoice
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'balance' => 500.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getOutstandingInvoicesForForecasting();

        $this->assertCount(1, $results);

        $row = $results[0];
        // Client has 2 paid invoices, both with 10-day delay
        $this->assertEqualsWithDelta(10, $row->client_avg_payment_days, 0.01);
        $this->assertEquals(2, $row->client_data_points);
        // Both paid before due_date, so late ratio = 0
        $this->assertEqualsWithDelta(0, $row->client_late_ratio, 0.001);
    }

    public function testOutstandingInvoicesNullAnalyticsForNewClient(): void
    {
        // No payment history for this client
        Invoice::factory()->create([
            'client_id' => $this->test_client_b->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'balance' => 500.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getOutstandingInvoicesForForecasting();

        $this->assertCount(1, $results);
        $this->assertNull($results[0]->client_avg_payment_days);
        $this->assertNull($results[0]->client_data_points);
    }

    // ─── getRecurringInvoiceProjections ─────────────────────────────

    public function testRecurringInvoiceProjectionsReturnsActiveOnly(): void
    {
        // Active recurring invoice
        $active = RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addMonth(),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'auto_bill_enabled' => true,
            'exchange_rate' => 1,
        ]);

        // Paused recurring invoice (should NOT be returned)
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'status_id' => RecurringInvoice::STATUS_PAUSED,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addMonth(),
            'remaining_cycles' => 5,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Completed recurring invoice (should NOT be returned)
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'status_id' => RecurringInvoice::STATUS_COMPLETED,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => null,
            'remaining_cycles' => 0,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getRecurringInvoiceProjections();

        $this->assertCount(1, $results);
        $this->assertEquals($active->id, $results[0]->id);
        $this->assertEquals(500.00, $results[0]->amount);
        $this->assertEquals(RecurringInvoice::FREQUENCY_MONTHLY, $results[0]->frequency_id);
        $this->assertEquals(-1, $results[0]->remaining_cycles);
    }

    public function testRecurringInvoiceProjectionsIncludesFrequencyData(): void
    {
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 1200.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_WEEKLY,
            'next_send_date' => '2026-04-07',
            'remaining_cycles' => 4,
            'is_deleted' => false,
            'auto_bill_enabled' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getRecurringInvoiceProjections();

        $this->assertCount(1, $results);
        $this->assertEquals(RecurringInvoice::FREQUENCY_WEEKLY, $results[0]->frequency_id);
        $this->assertStringStartsWith('2026-04-07', $results[0]->next_send_date);
        $this->assertEquals(4, $results[0]->remaining_cycles);
        $this->assertEquals(0, $results[0]->auto_bill_enabled);
    }

    // ─── getRecurringExpenseProjections ──────────────────────────────

    public function testRecurringExpenseProjectionsReturnsActiveOnly(): void
    {
        $active = RecurringExpense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 250.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addMonth(),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
        ]);

        // Paused (should NOT be returned)
        RecurringExpense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'status_id' => RecurringInvoice::STATUS_PAUSED,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addMonth(),
            'remaining_cycles' => 3,
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
        ]);

        $cs = $this->getService();
        $results = $cs->getRecurringExpenseProjections();

        $this->assertCount(1, $results);
        $this->assertEquals($active->id, $results[0]->id);
        $this->assertEquals(250.00, $results[0]->amount);
    }

    // ─── getUpcomingExpenses ────────────────────────────────────────

    public function testUpcomingExpensesFiltersDateRange(): void
    {
        // In range
        $in_range = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'date' => '2026-04-15',
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
        ]);

        // Out of range
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'date' => '2026-08-15',
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
        ]);

        $cs = $this->getService();
        $results = $cs->getUpcomingExpenses('2026-04-01', '2026-06-30');

        $expense_ids = collect($results)->pluck('id')->all();

        $this->assertContains($in_range->id, $expense_ids);
        $this->assertCount(1, $results);
    }

    public function testUpcomingExpensesCalculatesTaxForExclusiveTax(): void
    {
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'date' => '2026-04-15',
            'is_deleted' => false,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 10,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
        ]);

        $cs = $this->getService();
        $results = $cs->getUpcomingExpenses('2026-04-01', '2026-04-30');

        $this->assertCount(1, $results);
        // 100 + (100 * 10/100) = 110
        $this->assertEqualsWithDelta(110.00, $results[0]->amount, 0.01);
    }

    public function testUpcomingExpensesExcludesDeletedExpenses(): void
    {
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'date' => '2026-04-15',
            'is_deleted' => true,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
        ]);

        $cs = $this->getService();
        $results = $cs->getUpcomingExpenses('2026-04-01', '2026-04-30');

        $this->assertCount(0, $results);
    }

    // ─── getQuoteConversionHistory ──────────────────────────────────

    public function testQuoteConversionHistoryCalculatesRate(): void
    {
        // Converted quote
        $invoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'balance' => 500.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-02-01',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'status_id' => Quote::STATUS_CONVERTED,
            'date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'invoice_id' => $invoice->id,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Non-converted quote (sent)
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => '2026-01-20',
            'due_date' => '2026-02-20',
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Rejected quote
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'status_id' => Quote::STATUS_REJECTED,
            'date' => '2026-01-25',
            'due_date' => '2026-02-25',
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getQuoteConversionHistory();

        $this->assertCount(1, $results); // one client
        $row = $results[0];

        $this->assertEquals($this->test_client->id, $row->client_id);
        $this->assertEquals(3, $row->total_quotes);
        $this->assertEquals(1, $row->converted_quotes);
        $this->assertEqualsWithDelta(0.3333, $row->conversion_rate, 0.001);
        $this->assertEquals(1000.00, $row->total_value); // 500 + 300 + 200
        $this->assertEquals(500.00, $row->converted_value);
    }

    public function testQuoteConversionHistoryExcludesDraftQuotes(): void
    {
        // Draft quote (should NOT be counted)
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'status_id' => Quote::STATUS_DRAFT,
            'date' => '2026-01-01',
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getQuoteConversionHistory();

        $this->assertCount(0, $results);
    }

    // ─── getOpenQuotesForForecasting ────────────────────────────────

    public function testOpenQuotesReturnsSentAndApprovedOnly(): void
    {
        // Sent quote (should be returned)
        $sent = Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => '2026-03-01',
            'due_date' => '2026-04-01',
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Approved quote (should be returned)
        $approved = Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300.00,
            'status_id' => Quote::STATUS_APPROVED,
            'date' => '2026-03-05',
            'due_date' => '2026-04-05',
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Converted quote (should NOT be returned - has invoice_id)
        $invoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'balance' => 200.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-03-10',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'status_id' => Quote::STATUS_CONVERTED,
            'date' => '2026-03-10',
            'invoice_id' => $invoice->id,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getOpenQuotesForForecasting();

        $quote_ids = collect($results)->pluck('quote_id')->all();

        $this->assertContains($sent->id, $quote_ids);
        $this->assertContains($approved->id, $quote_ids);
        $this->assertCount(2, $results);
    }

    public function testOpenQuotesIncludeClientConversionRate(): void
    {
        // Create conversion history: 1 converted, 1 rejected
        $invoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'balance' => 100.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-01-15',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'status_id' => Quote::STATUS_CONVERTED,
            'date' => '2026-01-01',
            'invoice_id' => $invoice->id,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'status_id' => Quote::STATUS_REJECTED,
            'date' => '2026-01-05',
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Open quote to forecast
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 800.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => '2026-03-15',
            'due_date' => '2026-04-15',
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getOpenQuotesForForecasting();

        $this->assertCount(1, $results);
        // 1 converted out of 3 total (converted + rejected + the open one is status SENT, which is in the history subquery)
        // History subquery counts statuses 2,3,4,5 — that includes the open sent quote too
        // So: 3 total quotes with status in (2,3,4,5), 1 converted = 1/3
        $this->assertEqualsWithDelta(0.3333, $results[0]->client_conversion_rate, 0.001);
    }

    // ─── getPaymentDelayTrend ───────────────────────────────────────

    public function testPaymentDelayTrendGroupsByMonth(): void
    {
        // January: 10 day delay
        $this->createPaidInvoice($this->test_client, 100.00, '2026-01-05', '2026-01-15', '2026-01-20');

        // February: 5 day delay
        $this->createPaidInvoice($this->test_client, 200.00, '2026-02-05', '2026-02-10', '2026-02-20');

        // March: 20 day delay (late - due was Mar 15)
        $this->createPaidInvoice($this->test_client, 300.00, '2026-03-01', '2026-03-21', '2026-03-15');

        $cs = $this->getService();
        $results = $cs->getPaymentDelayTrend('2026-01-01', '2026-03-31');

        $this->assertCount(3, $results);

        $months = collect($results)->keyBy('month');

        $this->assertEqualsWithDelta(10, $months['2026-01']->avg_payment_days, 0.01);
        $this->assertEqualsWithDelta(5, $months['2026-02']->avg_payment_days, 0.01);
        $this->assertEqualsWithDelta(20, $months['2026-03']->avg_payment_days, 0.01);

        $this->assertEquals(0, $months['2026-01']->late_count);
        $this->assertEquals(0, $months['2026-02']->late_count);
        $this->assertEquals(1, $months['2026-03']->late_count);
    }

    public function testPaymentDelayTrendRespectsDateRange(): void
    {
        $this->createPaidInvoice($this->test_client, 100.00, '2025-06-01', '2025-06-10');
        $this->createPaidInvoice($this->test_client, 200.00, '2026-02-01', '2026-02-10');

        $cs = $this->getService();
        $results = $cs->getPaymentDelayTrend('2026-01-01', '2026-12-31');

        // Only the 2026-02 invoice should be included
        $this->assertCount(1, $results);
        $this->assertEquals('2026-02', $results[0]->month);
    }

    // ─── Cross-company isolation ────────────────────────────────────

    public function testQueriesAreIsolatedByCompany(): void
    {
        // Create data for a different company
        $other_settings = CompanySettings::defaults();
        $other_settings->currency_id = '1';

        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $other_settings,
        ]);

        $other_client_settings = ClientSettings::defaults();
        $other_client_settings->currency_id = '1';

        $other_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $other_company->id,
            'settings' => $other_client_settings,
        ]);

        // Paid invoice on the OTHER company
        $other_invoice = Invoice::factory()->create([
            'client_id' => $other_client->id,
            'user_id' => $this->user->id,
            'company_id' => $other_company->id,
            'amount' => 999.00,
            'balance' => 0,
            'paid_to_date' => 999.00,
            'status_id' => Invoice::STATUS_PAID,
            'date' => '2026-01-01',
            'due_date' => '2026-01-15',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $other_payment = Payment::factory()->create([
            'client_id' => $other_client->id,
            'user_id' => $this->user->id,
            'company_id' => $other_company->id,
            'amount' => 999.00,
            'applied' => 999.00,
            'status_id' => Payment::STATUS_COMPLETED,
            'date' => '2026-01-20',
            'is_deleted' => false,
            'currency_id' => 1,
        ]);

        $other_payment->invoices()->attach($other_invoice->id, ['amount' => 999.00]);

        // Paid invoice on OUR company
        $this->createPaidInvoice($this->test_client, 100.00, '2026-01-01', '2026-01-11');

        $cs = $this->getService();

        $delays = $cs->getClientPaymentDelays(null);
        $client_ids = collect($delays)->pluck('client_id')->unique()->all();

        $this->assertContains($this->test_client->id, $client_ids);
        $this->assertNotContains($other_client->id, $client_ids);

        $summary = $cs->getCompanyPaymentSummary();
        $this->assertEquals(1, $summary[0]->total_invoices);

        $other_client->forceDelete();
    }

    // ─── Chart Queries: MRR ─────────────────────────────────────────

    public function testMrrChartQueryReturnsRecurringInvoiceRevenue(): void
    {
        // Monthly $500 recurring invoice, next fires in 1 month
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $start = now()->format('Y-m-d');
        $end = now()->addMonths(3)->format('Y-m-d');
        $results = $cs->getMrrChartQuery($start, $end, 1);

        // Should project $500 into at least 3 months
        $this->assertGreaterThanOrEqual(3, count($results));

        foreach ($results as $row) {
            $this->assertEqualsWithDelta(500.00, $row->total, 0.01);
        }
    }

    public function testAggregateMrrChartConvertsExchangeRate(): void
    {
        // Foreign currency recurring invoice with exchange_rate 2:1
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => 1,
            'is_deleted' => false,
            'exchange_rate' => 2,
        ]);

        $cs = $this->getService();
        $start = now()->format('Y-m-d');
        $end = now()->addMonths(2)->format('Y-m-d');
        $results = $cs->getAggregateMrrChartQuery($start, $end);

        $this->assertGreaterThanOrEqual(1, count($results));
        // 200 / 2 = 100 in company currency
        foreach ($results as $row) {
            $this->assertEqualsWithDelta(100.00, $row->total, 0.01);
        }
    }

    public function testMrrChartQueryNormalizesQuarterlyToMonthly(): void
    {
        // Quarterly $300 recurring invoice → should show $100/month MRR
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_THREE_MONTHS,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $start = now()->format('Y-m-d');
        $end = now()->addMonths(6)->format('Y-m-d');
        $results = $cs->getMrrChartQuery($start, $end, 1);

        $this->assertGreaterThanOrEqual(6, count($results));

        foreach ($results as $row) {
            $this->assertEqualsWithDelta(100.00, $row->total, 0.01);
        }
    }

    public function testMrrChartQueryNormalizesAnnualToMonthly(): void
    {
        // Annual $1200 recurring invoice → should show $100/month MRR
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 1200.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_ANNUALLY,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $start = now()->format('Y-m-d');
        $end = now()->addMonths(12)->format('Y-m-d');
        $results = $cs->getMrrChartQuery($start, $end, 1);

        $this->assertGreaterThanOrEqual(12, count($results));

        foreach ($results as $row) {
            $this->assertEqualsWithDelta(100.00, $row->total, 0.01);
        }
    }

    public function testMrrChartQueryRespectsFiniteCycles(): void
    {
        // Quarterly $300, remaining_cycles=2 → MRR for ~6 months then stops
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_THREE_MONTHS,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => 2,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $start = now()->format('Y-m-d');
        $end = now()->addMonths(12)->format('Y-m-d');
        $results = $cs->getMrrChartQuery($start, $end, 1);

        // Should have MRR buckets only within the 2-cycle window (~7 months from chart start), not all 12
        $this->assertLessThan(count(range(0, 12)), count($results));

        foreach ($results as $row) {
            $this->assertEqualsWithDelta(100.00, $row->total, 0.01);
        }
    }

    public function testAggregateMrrChartNormalizesQuarterlyWithExchangeRate(): void
    {
        // Quarterly $600 with exchange_rate 2 → $300 in company currency → $100/month MRR
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 600.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_THREE_MONTHS,
            'next_send_date' => now()->addDays(5)->format('Y-m-d'),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'exchange_rate' => 2,
        ]);

        $cs = $this->getService();
        $start = now()->format('Y-m-d');
        $end = now()->addMonths(6)->format('Y-m-d');
        $results = $cs->getAggregateMrrChartQuery($start, $end);

        $this->assertGreaterThanOrEqual(6, count($results));

        foreach ($results as $row) {
            $this->assertEqualsWithDelta(100.00, $row->total, 0.01);
        }
    }

    // ─── Chart Queries: MRR/ARR Totals ──────────────────────────────

    public function testMrrTotalQueryNormalizesFrequencies(): void
    {
        // Monthly recurring: $100/month → MRR = 100
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addMonth(),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Annual recurring: $1200/year → MRR = 100
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 1200.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_ANNUALLY,
            'next_send_date' => now()->addYear(),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getMrrTotalQuery();

        $this->assertCount(1, $results);
        // MRR = 100 (monthly) + 100 (1200/12) = 200
        $this->assertEqualsWithDelta(200.00, $results[0]->mrr, 0.01);
        // ARR = 200 * 12 = 2400
        $this->assertEqualsWithDelta(2400.00, $results[0]->arr, 0.01);
    }

    public function testMrrTotalExcludesPausedAndCompleted(): void
    {
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addMonth(),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'status_id' => RecurringInvoice::STATUS_PAUSED,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addMonth(),
            'remaining_cycles' => 5,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getMrrTotalQuery();

        $this->assertCount(1, $results);
        $this->assertEqualsWithDelta(100.00, $results[0]->mrr, 0.01);
    }

    public function testAggregateMrrTotalConvertsExchangeRate(): void
    {
        RecurringInvoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'status_id' => RecurringInvoice::STATUS_ACTIVE,
            'frequency_id' => RecurringInvoice::FREQUENCY_MONTHLY,
            'next_send_date' => now()->addMonth(),
            'remaining_cycles' => -1,
            'is_deleted' => false,
            'exchange_rate' => 2, // foreign currency
        ]);

        $cs = $this->getService();
        $results = $cs->getAggregateMrrTotalQuery();

        $this->assertCount(1, $results);
        // 200 / 2 = 100 MRR in company currency
        $this->assertEqualsWithDelta(100.00, $results[0]->mrr, 0.01);
        $this->assertEqualsWithDelta(1200.00, $results[0]->arr, 0.01);
    }

    // ─── Chart Queries: Payment Delay Chart ─────────────────────────

    public function testPaymentDelayChartReturnsMonthlyAverage(): void
    {
        // Jan: 10 day delay, Feb: 20 day delay
        $this->createPaidInvoice($this->test_client, 100.00, '2026-01-05', '2026-01-15');
        $this->createPaidInvoice($this->test_client, 200.00, '2026-02-05', '2026-02-25');

        $cs = $this->getService();
        $results = $cs->getPaymentDelayChartQuery('2026-01-01', '2026-12-31', 1);

        $this->assertCount(2, $results);

        $months = collect($results)->keyBy('date');
        $this->assertEqualsWithDelta(10, $months['2026-01-01']->total, 0.01);
        $this->assertEqualsWithDelta(20, $months['2026-02-01']->total, 0.01);
    }

    public function testAggregatePaymentDelayChartWorks(): void
    {
        $this->createPaidInvoice($this->test_client, 100.00, '2026-01-05', '2026-01-15');

        $cs = $this->getService();
        $results = $cs->getAggregatePaymentDelayChartQuery('2026-01-01', '2026-12-31');

        $this->assertCount(1, $results);
        $this->assertEqualsWithDelta(10, $results[0]->total, 0.01);
    }

    // ─── Chart Queries: Quote Pipeline ──────────────────────────────

    public function testQuotePipelineChartGroupsByMonth(): void
    {
        $thisMonth = now()->format('Y-m');
        $nextMonth = now()->addMonth()->format('Y-m');

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => null,
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300.00,
            'status_id' => Quote::STATUS_APPROVED,
            'date' => now()->format('Y-m-d'),
            'due_date' => null,
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => now()->addMonth()->format('Y-m-01'),
            'due_date' => null,
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        // Date range is ignored — pipeline shows all actionable quotes
        $results = $cs->getQuotePipelineChartQuery('2020-01-01', '2030-12-31', 1);

        $this->assertGreaterThanOrEqual(2, count($results));

        $dates = collect($results)->keyBy('date');
        $this->assertEquals(800.00, $dates[now()->format('Y-m-01')]->total);
        $this->assertEquals(200.00, $dates[now()->addMonth()->format('Y-m-01')]->total);
    }

    public function testQuotePipelineExcludesConvertedAndExpired(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'balance' => 500.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Converted quote (should NOT appear — already an invoice)
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'status_id' => Quote::STATUS_CONVERTED,
            'date' => now()->format('Y-m-d'),
            'invoice_id' => $invoice->id,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Expired quote (due_date in the past, should NOT appear)
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 400.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->subDays(5)->format('Y-m-d'),
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Open quote with no due_date (never expires, should appear)
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => null,
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // Open quote with future due_date (not expired, should appear)
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'status_id' => Quote::STATUS_APPROVED,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getQuotePipelineChartQuery('2020-01-01', '2030-12-31', 1);

        $this->assertCount(1, $results);
        $this->assertEquals(500.00, $results[0]->total); // 300 + 200 (converted and expired excluded)
    }

    // ─── Chart Queries: Late Payment Rate ───────────────────────────

    public function testLatePaymentRateChartReturnsRatio(): void
    {
        // Oct: 1 invoice paid on time, 1 invoice paid late
        $this->createPaidInvoice($this->test_client, 100.00, '2026-10-01', '2026-10-10', '2026-10-15'); // on time
        $this->createPaidInvoice($this->test_client, 200.00, '2026-10-05', '2026-10-25', '2026-10-10'); // late (paid 25th, due 10th)

        $cs = $this->getService();

        // Use aggregate query (no currency filter)
        $results = $cs->getAggregateLatePaymentRateChartQuery('2026-10-01', '2026-10-31');

        $this->assertCount(1, $results);

        $oct = $results[0];
        $this->assertEquals('2026-10-01', $oct->date);
        // 1 late out of 2 = 0.5
        $this->assertEqualsWithDelta(0.5, (float) $oct->total, 0.01);
    }

    // ─── Chart Queries: AR Aging Buckets ────────────────────────────

    public function testAgingBucketTotalsDistributeCorrectly(): void
    {
        // Current (not yet due)
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 100.00,
            'balance' => 100.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // 0-30 days overdue
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'balance' => 200.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->subDays(40)->format('Y-m-d'),
            'due_date' => now()->subDays(15)->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // 31-60 days overdue
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 300.00,
            'balance' => 300.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->subDays(70)->format('Y-m-d'),
            'due_date' => now()->subDays(45)->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        // 120+ days overdue
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 400.00,
            'balance' => 400.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->subDays(200)->format('Y-m-d'),
            'due_date' => now()->subDays(150)->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getAgingBucketTotals();

        $this->assertCount(1, $results); // one currency

        $row = $results[0];
        $this->assertEqualsWithDelta(100.00, $row->current_amount, 0.01);
        $this->assertEqualsWithDelta(200.00, $row->age_0_30, 0.01);
        $this->assertEqualsWithDelta(300.00, $row->age_31_60, 0.01);
        $this->assertEqualsWithDelta(400.00, $row->age_120_plus, 0.01);
    }

    public function testAgingBucketExcludesPaidInvoices(): void
    {
        // Paid invoice (should NOT appear)
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 500.00,
            'balance' => 0,
            'paid_to_date' => 500.00,
            'status_id' => Invoice::STATUS_PAID,
            'date' => now()->subDays(30)->format('Y-m-d'),
            'due_date' => now()->subDays(10)->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getAgingBucketTotals();

        $this->assertCount(0, $results);
    }

    public function testAggregateAgingBucketConvertsExchangeRate(): void
    {
        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 200.00,
            'balance' => 200.00,
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'due_date' => now()->addDays(10)->format('Y-m-d'),
            'is_deleted' => false,
            'exchange_rate' => 2, // foreign currency
        ]);

        $cs = $this->getService();
        $results = $cs->getAggregateAgingBucketTotals();

        $this->assertCount(1, $results);
        // 200 / 2 = 100 in company currency
        $this->assertEqualsWithDelta(100.00, $results[0]->current_amount, 0.01);
    }

    // ─── Service Layer: analytics_summary / analytics_totals ────────

    public function testAnalyticsSummaryReturnsExpectedStructure(): void
    {
        $cs = $this->getService();
        $results = $cs->analytics_summary('2026-01-01', '2026-12-31');

        $this->assertArrayHasKey('start_date', $results);
        $this->assertArrayHasKey('end_date', $results);
        $this->assertArrayHasKey(999, $results);
        $this->assertArrayHasKey('mrr', $results[999]);
        $this->assertArrayHasKey('payment_delay', $results[999]);
        $this->assertArrayHasKey('quote_pipeline', $results[999]);
        $this->assertArrayHasKey('late_payment_rate', $results[999]);
    }

    public function testAnalyticsTotalsReturnsExpectedStructure(): void
    {
        $cs = $this->getService();
        $results = $cs->analytics_totals('2026-01-01', '2026-12-31');

        $this->assertArrayHasKey('currencies', $results);
        $this->assertArrayHasKey('start_date', $results);
        $this->assertArrayHasKey('end_date', $results);
        $this->assertArrayHasKey(999, $results);
        $this->assertArrayHasKey('mrr', $results[999]);
        $this->assertArrayHasKey('aging', $results[999]);
        $this->assertArrayHasKey('payment_analytics', $results[999]);
    }

    // ─── API Endpoints ──────────────────────────────────────────────

    public function testAnalyticsSummaryEndpoint(): void
    {
        $data = [
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/charts/analytics_summary', $data);

        $response->assertStatus(200);
    }

    public function testAnalyticsTotalsEndpoint(): void
    {
        $data = [
            'start_date' => now()->subDays(30)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/charts/analytics_totals', $data);

        $response->assertStatus(200);
    }

    // ─── Quote Pipeline: Non-Zero Results ───────────────────────────

    public function testQuotePipelineReturnsNonZeroForActionableQuotes(): void
    {
        // Open quote, no due_date (never expires)
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 750.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'due_date' => null,
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();

        // Per-currency
        $results = $cs->getQuotePipelineChartQuery('2020-01-01', '2030-12-31', 1);
        $this->assertNotEmpty($results, 'Quote pipeline per-currency should return data');
        $total = array_sum(array_map(fn ($r) => (float) $r->total, $results));
        $this->assertGreaterThan(0, $total, 'Quote pipeline per-currency total should be > 0');

        // Aggregate
        $aggregate = $cs->getAggregateQuotePipelineChartQuery('2020-01-01', '2030-12-31');
        $this->assertNotEmpty($aggregate, 'Quote pipeline aggregate should return data');
        $aggTotal = array_sum(array_map(fn ($r) => (float) $r->total, $aggregate));
        $this->assertGreaterThan(0, $aggTotal, 'Quote pipeline aggregate total should be > 0');
    }

    public function testQuotePipelineViaAnalyticsSummaryReturnsNonZero(): void
    {
        // Open quote with future due_date
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 1200.00,
            'status_id' => Quote::STATUS_APPROVED,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(60)->format('Y-m-d'),
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $summary = $cs->analytics_summary(now()->subYear()->format('Y-m-d'), now()->format('Y-m-d'));

        // Aggregate (999) quote_pipeline should have data
        $this->assertNotEmpty($summary[999]['quote_pipeline'], 'analytics_summary.999.quote_pipeline should not be empty');

        $total = array_sum(array_map(fn ($r) => (float) $r->total, $summary[999]['quote_pipeline']));
        $this->assertGreaterThanOrEqual(1200.00, $total, 'analytics_summary quote_pipeline total should include the open quote');
    }

    public function testQuotePipelineExcludesFullyExpiredQuotes(): void
    {
        // Only create an expired quote for this test company — no actionable quotes
        Quote::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'amount' => 999.00,
            'status_id' => Quote::STATUS_SENT,
            'date' => now()->subDays(60)->format('Y-m-d'),
            'due_date' => now()->subDays(30)->format('Y-m-d'), // expired 30 days ago
            'invoice_id' => null,
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        $cs = $this->getService();
        $results = $cs->getQuotePipelineChartQuery('2020-01-01', '2030-12-31', 1);

        // The only quote we created is expired, so pipeline should be empty
        $this->assertEmpty($results, 'Pipeline should be empty when only expired quotes exist');
    }
}
