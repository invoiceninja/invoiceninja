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

use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Chart\ChartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Verifies that the ninja:create-analytics-test-data command produces
 * a dataset whose values match the expected analytics output.
 *
 * Runs the command with --refresh, then queries ChartService and raw
 * models to confirm every metric is non-zero, deterministic, and
 * consistent between per-currency (USD) and aggregate (ALL) views.
 */
class AnalyticsTestDataVerificationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private Company $analyticsCompany;
    private User $analyticsUser;
    private ChartService $cs;
    private string $startDate;
    private string $endDate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();

        $this->artisan('ninja:create-analytics-test-data', ['--refresh' => true]);

        $this->analyticsCompany = Company::where('settings->name', 'Analytics')->firstOrFail();
        $this->analyticsUser = User::where('email', 'analytics@example.com')->firstOrFail();

        $year = now()->year;
        $this->startDate = "{$year}-01-01";
        $this->endDate = "{$year}-12-31";

        $this->cs = new ChartService($this->analyticsCompany, $this->analyticsUser, true);
    }

    // =======================================================================
    // INVOICE COUNTS & STATUSES
    // =======================================================================

    public function testInvoiceCounts(): void
    {
        $invoices = Invoice::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        $this->assertCount(36, $invoices, 'Should have 36 invoices (3 per month x 12 months)');

        $paid = $invoices->where('status_id', Invoice::STATUS_PAID)->count();
        $sent = $invoices->where('status_id', Invoice::STATUS_SENT)->count();
        $draft = $invoices->where('status_id', Invoice::STATUS_DRAFT)->count();

        $this->assertEquals(18, $paid, '18 paid (2/month, months 1-9)');
        $this->assertEquals(15, $sent, '15 sent (1/month months 1-9, 3/month months 10-11)');
        $this->assertEquals(3, $draft, '3 draft (month 12)');
    }

    public function testEveryMonthHasThreeInvoices(): void
    {
        $year = now()->year;
        $invoices = Invoice::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        for ($month = 1; $month <= 12; $month++) {
            $monthInvoices = $invoices->filter(function ($inv) use ($year, $month) {
                $d = Carbon::parse($inv->date);
                return $d->year === $year && $d->month === $month;
            });
            $this->assertCount(3, $monthInvoices, "Month {$month} should have exactly 3 invoices");
        }
    }

    public function testAllInvoicesHaveDueDates(): void
    {
        $withoutDue = Invoice::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->whereNull('due_date')
            ->count();

        $this->assertEquals(0, $withoutDue, 'All invoices must have a due_date for late payment calculation');
    }

    // =======================================================================
    // PAYMENT DELAY — VARYING PER MONTH (NOT FLAT)
    // =======================================================================

    public function testPaymentDelayChartIsNotFlat(): void
    {
        $summary = $this->cs->analytics_summary($this->startDate, $this->endDate);
        $delayPoints = $summary[999]['payment_delay'];

        $this->assertCount(9, $delayPoints, '9 months of payment delay data (months 1-9)');

        $values = array_map(fn ($p) => (float) $p->total, $delayPoints);
        $uniqueValues = array_unique($values);

        $this->assertGreaterThan(3, count($uniqueValues), 'Payment delay should have >3 distinct values');

        // Verify exact expected delays from monthlyDelays config:
        // [8,20]=14, [12,35]=23.5, [15,28]=21.5, [10,42]=26, [18,45]=31.5
        // [22,38]=30, [7,15]=11, [25,50]=37.5, [14,32]=23
        $expected = [14.00, 23.50, 21.50, 26.00, 31.50, 30.00, 11.00, 37.50, 23.00];
        foreach ($expected as $i => $val) {
            $this->assertEquals($val, $values[$i], "Month " . ($i + 1) . " delay should be {$val}");
        }
    }

    // =======================================================================
    // LATE PAYMENT RATE — VARYING PER MONTH (NOT FLAT)
    // =======================================================================

    public function testLatePaymentRateChartIsNotFlat(): void
    {
        $summary = $this->cs->analytics_summary($this->startDate, $this->endDate);
        $latePoints = $summary[999]['late_payment_rate'];

        $this->assertGreaterThanOrEqual(9, count($latePoints), 'At least 9 months of late payment data');

        $values = array_map(fn ($p) => (float) $p->total, $latePoints);
        $uniqueValues = array_unique($values);

        $this->assertGreaterThan(1, count($uniqueValues), 'Late payment rate must not be flat');

        // Verify some months have 0% and some >0%
        $zeroMonths = array_filter($values, fn ($v) => $v == 0);
        $nonZeroMonths = array_filter($values, fn ($v) => $v > 0);
        $this->assertNotEmpty($zeroMonths, 'Some months should have 0% late rate');
        $this->assertNotEmpty($nonZeroMonths, 'Some months should have >0% late rate');
    }

    // =======================================================================
    // OUTSTANDING — NON-ZERO, GROWING, IN EVERY MONTH
    // =======================================================================

    public function testOutstandingChartIsNonZeroAndGrowing(): void
    {
        $chart = $this->cs->chart_summary($this->startDate, $this->endDate);
        $outstanding = $chart[999]['outstanding'];

        $this->assertGreaterThanOrEqual(9, count($outstanding), 'At least 9 months of outstanding data');

        $values = array_map(fn ($p) => (float) $p->total, $outstanding);
        $this->assertGreaterThan(0, $values[0], 'First month outstanding should be > 0');
        $this->assertGreaterThan($values[0], end($values), 'Outstanding should grow cumulatively');
    }

    public function testOutstandingTotalMatchesSentInvoiceBalances(): void
    {
        $totals = $this->cs->totals($this->startDate, $this->endDate);
        $outstandingAmount = (float) ($totals[999]['outstanding']->amount ?? 0);
        $outstandingCount = (int) ($totals[999]['outstanding']->outstanding_count ?? 0);

        $expectedBalance = (float) Invoice::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->sum('balance');

        $this->assertEquals(15, $outstandingCount, '15 outstanding invoices');
        $this->assertEqualsWithDelta($expectedBalance, $outstandingAmount, 0.01);
        $this->assertGreaterThan(0, $outstandingAmount);
    }

    // =======================================================================
    // COMPANY PAYMENT SUMMARY — EXACT VALUES
    // =======================================================================

    public function testCompanyPaymentSummaryExactValues(): void
    {
        $totals = $this->cs->analytics_totals($this->startDate, $this->endDate);
        $pa = $totals[999]['payment_analytics'];

        $this->assertEquals(18, $pa->total_invoices, '18 paid invoices');
        $this->assertEquals(6, $pa->late_invoices, '6 late (delay > 30 days: months 2,4,5,6,8,9)');
        $this->assertEquals(0.3333, (float) $pa->late_payment_ratio, 'Late ratio = 6/18 = 0.3333');
        // sum of delays: 8+20+12+35+15+28+10+42+18+45+22+38+7+15+25+50+14+32 = 436
        // avg = 436/18 = 24.2222
        $this->assertEquals(24.22, (float) $pa->avg_payment_days, 'Avg payment days = 24.22');
    }

    // =======================================================================
    // CURRENCY PARITY — USD vs ALL must match for single-currency data
    // =======================================================================

    public function testCurrencyParityTotals(): void
    {
        $totals = $this->cs->totals($this->startDate, $this->endDate);

        // USD = key 1, ALL = key 999
        $usdInvoiced = (float) ($totals[1]['invoices']->invoiced_amount ?? 0);
        $allInvoiced = (float) ($totals[999]['invoices']->invoiced_amount ?? 0);
        $this->assertEqualsWithDelta($usdInvoiced, $allInvoiced, 0.01, 'USD and ALL invoiced totals must match');

        $usdOutstanding = (float) ($totals[1]['outstanding']->amount ?? 0);
        $allOutstanding = (float) ($totals[999]['outstanding']->amount ?? 0);
        $this->assertEqualsWithDelta($usdOutstanding, $allOutstanding, 0.01, 'USD and ALL outstanding must match');

        $usdRevenue = (float) ($totals[1]['revenue']->paid_to_date ?? 0);
        $allRevenue = (float) ($totals[999]['revenue']->paid_to_date ?? 0);
        $this->assertEqualsWithDelta($usdRevenue, $allRevenue, 0.01, 'USD and ALL revenue must match');

        $usdExpenses = (float) ($totals[1]['expenses']->amount ?? 0);
        $allExpenses = (float) ($totals[999]['expenses']->amount ?? 0);
        $this->assertEqualsWithDelta($usdExpenses, $allExpenses, 0.01, 'USD and ALL expenses must match');
    }

    public function testCurrencyParityAnalyticsTotals(): void
    {
        $at = $this->cs->analytics_totals($this->startDate, $this->endDate);

        // MRR
        $this->assertEquals((float) ($at[1]['mrr']->mrr ?? 0), (float) ($at[999]['mrr']->mrr ?? 0), 'USD and ALL MRR must match');
        $this->assertEquals((float) ($at[1]['mrr']->arr ?? 0), (float) ($at[999]['mrr']->arr ?? 0), 'USD and ALL ARR must match');

        // Recurring expenses
        $this->assertEquals(
            (float) ($at[1]['recurring_expenses']->monthly_total ?? 0),
            (float) ($at[999]['recurring_expenses']->monthly_total ?? 0),
            'USD and ALL recurring expenses must match'
        );

        // Payment analytics — this is the key fix being tested
        $usdPa = $at[1]['payment_analytics'] ?? new \stdClass();
        $allPa = $at[999]['payment_analytics'] ?? new \stdClass();

        $this->assertNotEmpty((array) $usdPa, 'USD payment_analytics must NOT be empty');
        $this->assertNotEmpty((array) $allPa, 'ALL payment_analytics must NOT be empty');

        $this->assertEquals(
            (float) ($usdPa->avg_payment_days ?? 0),
            (float) ($allPa->avg_payment_days ?? 0),
            'USD and ALL avg_payment_days must match'
        );
        $this->assertEquals(
            (float) ($usdPa->late_payment_ratio ?? 0),
            (float) ($allPa->late_payment_ratio ?? 0),
            'USD and ALL late_payment_ratio must match'
        );
        $this->assertEquals(
            (int) ($usdPa->total_invoices ?? 0),
            (int) ($allPa->total_invoices ?? 0),
            'USD and ALL total_invoices must match'
        );
    }

    public function testCurrencyParityChartSummary(): void
    {
        $chart = $this->cs->chart_summary($this->startDate, $this->endDate);

        $this->assertCount(count($chart[1]['invoices']), $chart[999]['invoices'], 'USD and ALL invoice chart point count must match');
        $this->assertCount(count($chart[1]['outstanding']), $chart[999]['outstanding'], 'USD and ALL outstanding chart point count must match');
        $this->assertCount(count($chart[1]['payments']), $chart[999]['payments'], 'USD and ALL payment chart point count must match');
        $this->assertCount(count($chart[1]['expenses']), $chart[999]['expenses'], 'USD and ALL expense chart point count must match');
    }

    public function testCurrencyParityAnalyticsSummary(): void
    {
        $summary = $this->cs->analytics_summary($this->startDate, $this->endDate);

        $this->assertCount(
            count($summary[1]['payment_delay']),
            $summary[999]['payment_delay'],
            'USD and ALL payment_delay chart point count must match'
        );
        $this->assertCount(
            count($summary[1]['late_payment_rate']),
            $summary[999]['late_payment_rate'],
            'USD and ALL late_payment_rate chart point count must match'
        );

        // Values should also match (single currency = no exchange rate differences)
        $usdDelays = array_map(fn ($p) => (float) $p->total, $summary[1]['payment_delay']);
        $allDelays = array_map(fn ($p) => (float) $p->total, $summary[999]['payment_delay']);
        $this->assertEquals($usdDelays, $allDelays, 'USD and ALL payment_delay values must match');

        $usdLate = array_map(fn ($p) => (float) $p->total, $summary[1]['late_payment_rate']);
        $allLate = array_map(fn ($p) => (float) $p->total, $summary[999]['late_payment_rate']);
        $this->assertEquals($usdLate, $allLate, 'USD and ALL late_payment_rate values must match');
    }

    // =======================================================================
    // TOTALS — INVOICED, PAID, EXPENSES (exact values)
    // =======================================================================

    public function testInvoicedTotal(): void
    {
        $totals = $this->cs->totals($this->startDate, $this->endDate);
        $invoiced = (float) ($totals[999]['invoices']->invoiced_amount ?? 0);
        $this->assertEqualsWithDelta(43230.00, $invoiced, 0.01, 'Invoiced = $43,230 (excludes 3 drafts)');
    }

    public function testRevenueTotal(): void
    {
        $totals = $this->cs->totals($this->startDate, $this->endDate);
        $revenue = (float) ($totals[999]['revenue']->paid_to_date ?? 0);
        $this->assertEqualsWithDelta(24420.00, $revenue, 0.01, 'Revenue = $24,420');
    }

    public function testExpenseTotal(): void
    {
        $totals = $this->cs->totals($this->startDate, $this->endDate);
        $expenses = (float) ($totals[999]['expenses']->amount ?? 0);
        $this->assertEqualsWithDelta(15700.00, $expenses, 0.01, 'Expenses = $15,700');
    }

    // =======================================================================
    // RECURRING — MRR/ARR & RECURRING EXPENSES
    // =======================================================================

    public function testMrrAndArr(): void
    {
        $totals = $this->cs->analytics_totals($this->startDate, $this->endDate);
        $mrr = $totals[999]['mrr'];

        $this->assertEquals(1650.00, (float) $mrr->mrr, 'MRR = $1,650');
        $this->assertEquals(19800.00, (float) $mrr->arr, 'ARR = $19,800');
    }

    public function testRecurringExpenses(): void
    {
        $totals = $this->cs->analytics_totals($this->startDate, $this->endDate);
        $re = $totals[999]['recurring_expenses'];

        $this->assertEquals(700.00, (float) $re->monthly_total);
        $this->assertEquals(8400.00, (float) $re->annual_total);
        $this->assertEquals(3, (int) $re->count);
    }

    public function testRecurringInvoiceStatuses(): void
    {
        $ris = RecurringInvoice::where('company_id', $this->analyticsCompany->id)->get();
        $this->assertCount(3, $ris);

        $active = $ris->where('status_id', RecurringInvoice::STATUS_ACTIVE)->count();
        $paused = $ris->where('status_id', RecurringInvoice::STATUS_PAUSED)->count();

        $this->assertEquals(2, $active, '2 active recurring invoices');
        $this->assertEquals(1, $paused, '1 paused recurring invoice');
    }

    public function testRecurringExpenseStatuses(): void
    {
        $res = RecurringExpense::where('company_id', $this->analyticsCompany->id)->get();
        $this->assertCount(3, $res);

        foreach ($res as $re) {
            $this->assertEquals(RecurringInvoice::STATUS_ACTIVE, $re->status_id, 'All recurring expenses should be active');
            $this->assertEquals(-1, $re->remaining_cycles, 'All recurring expenses should be indefinite');
        }
    }

    // =======================================================================
    // QUOTES
    // =======================================================================

    public function testQuoteCounts(): void
    {
        $quotes = Quote::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        $this->assertCount(12, $quotes, '12 quotes (1/month)');
        $this->assertEquals(8, $quotes->where('status_id', Quote::STATUS_APPROVED)->count(), '8 approved');
        $this->assertEquals(4, $quotes->where('status_id', Quote::STATUS_SENT)->count(), '4 sent');
    }

    public function testQuotePipelineChartHasData(): void
    {
        $summary = $this->cs->analytics_summary($this->startDate, $this->endDate);
        $this->assertNotEmpty($summary[999]['quote_pipeline'], 'Quote pipeline chart should have data');
    }

    // =======================================================================
    // EXPENSES
    // =======================================================================

    public function testExpenseCounts(): void
    {
        $count = Expense::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->count();
        $this->assertEquals(24, $count, '24 expenses (2/month x 12)');
    }

    public function testExpensesSpreadAcrossAllMonths(): void
    {
        $year = now()->year;
        $expenses = Expense::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        for ($month = 1; $month <= 12; $month++) {
            $monthExpenses = $expenses->filter(function ($exp) use ($year, $month) {
                $d = Carbon::parse($exp->date);
                return $d->year === $year && $d->month === $month;
            });
            $this->assertCount(2, $monthExpenses, "Month {$month} should have 2 expenses");
        }
    }

    // =======================================================================
    // PROJECTS & TASKS
    // =======================================================================

    public function testProjectCounts(): void
    {
        $projects = Project::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        $this->assertCount(5, $projects);

        foreach ($projects as $project) {
            $this->assertGreaterThan(0, $project->budgeted_hours);
            $this->assertGreaterThan(0, $project->current_hours, "Project {$project->name} must have current_hours set");
            $this->assertNotNull($project->task_rate);
            $this->assertNotNull($project->due_date);
            $this->assertNotNull($project->client_id);
        }
    }

    public function testProjectCurrentHoursMatchTaskDurations(): void
    {
        $projects = Project::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        foreach ($projects as $project) {
            $taskHours = (int) round(
                Task::where('project_id', $project->id)->sum('duration') / 3600
            );
            $this->assertEquals($taskHours, $project->current_hours,
                "Project {$project->name} current_hours should match sum of task durations");
        }
    }

    public function testTasksHaveStatusAndTimeLogs(): void
    {
        $tasks = Task::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        $this->assertCount(20, $tasks, '20 tasks (4/project x 5 projects)');

        $statuses = TaskStatus::where('company_id', $this->analyticsCompany->id)->pluck('id')->toArray();
        $this->assertCount(4, $statuses, '4 task statuses');

        foreach ($tasks as $task) {
            $this->assertNotNull($task->status_id, "Task must have status_id");
            $this->assertContains($task->status_id, $statuses, "Task status_id must be valid");
            $this->assertNotNull($task->project_id);
            $this->assertNotNull($task->client_id);
            $this->assertGreaterThan(0, $task->duration);
            $this->assertNotNull($task->number, "Task must have a number");

            $timeLog = json_decode($task->time_log, true);
            $this->assertNotEmpty($timeLog);

            foreach ($timeLog as $entry) {
                $this->assertCount(2, $entry);
                $this->assertGreaterThan($entry[0], $entry[1], 'End > start');
            }
        }
    }

    public function testTaskStatusDistribution(): void
    {
        $statuses = TaskStatus::where('company_id', $this->analyticsCompany->id)
            ->orderBy('status_order')
            ->get();

        $doneId = $statuses->firstWhere('status_order', 4)->id;
        $inProgressId = $statuses->firstWhere('status_order', 3)->id;
        $backlogId = $statuses->firstWhere('status_order', 1)->id;

        $tasks = Task::where('company_id', $this->analyticsCompany->id)->get();

        $done = $tasks->where('status_id', $doneId)->count();
        $inProgress = $tasks->where('status_id', $inProgressId)->count();
        $backlog = $tasks->where('status_id', $backlogId)->count();

        $this->assertGreaterThan(0, $done, 'Should have Done tasks');
        $this->assertGreaterThan(0, $inProgress, 'Should have In Progress tasks');
        $this->assertGreaterThan(0, $backlog, 'Should have Backlog tasks');
    }

    public function testProjectAnalyticsEndpoint(): void
    {
        $pa = $this->cs->project_analytics();

        $this->assertArrayHasKey('budget_summary', $pa);
        $this->assertArrayHasKey('profitability', $pa);
        $this->assertCount(5, $pa['budget_summary']);
        $this->assertCount(5, $pa['profitability']);

        foreach ($pa['budget_summary'] as $p) {
            $this->assertGreaterThan(0, $p->total_tasks);
            $this->assertGreaterThan(0, $p->budgeted_hours);
            $this->assertGreaterThan(0, $p->current_hours);
            $this->assertGreaterThan(0, $p->utilization);
        }

        $withRevenue = array_filter($pa['profitability'], fn ($p) => $p->invoiced_amount > 0);
        $withExpenses = array_filter($pa['profitability'], fn ($p) => $p->expense_amount > 0);
        $this->assertNotEmpty($withRevenue, 'Projects should have invoiced revenue');
        $this->assertNotEmpty($withExpenses, 'Projects should have expenses');
    }

    // =======================================================================
    // CHART DATA POINTS — ALL ENDPOINTS PRODUCE DATA
    // =======================================================================

    public function testAllChartEndpointsProduceData(): void
    {
        $chart = $this->cs->chart_summary($this->startDate, $this->endDate);

        $this->assertNotEmpty($chart[999]['invoices']);
        $this->assertNotEmpty($chart[999]['outstanding']);
        $this->assertNotEmpty($chart[999]['payments']);
        $this->assertNotEmpty($chart[999]['expenses']);

        $summary = $this->cs->analytics_summary($this->startDate, $this->endDate);

        $this->assertNotEmpty($summary[999]['payment_delay']);
        $this->assertNotEmpty($summary[999]['late_payment_rate']);
        $this->assertNotEmpty($summary[999]['mrr']);
        $this->assertNotEmpty($summary[999]['quote_pipeline']);
    }

    public function testPerCurrencyChartEndpointsProduceData(): void
    {
        $chart = $this->cs->chart_summary($this->startDate, $this->endDate);

        $this->assertNotEmpty($chart[1]['invoices'], 'USD invoice chart should have data');
        $this->assertNotEmpty($chart[1]['outstanding'], 'USD outstanding chart should have data');
        $this->assertNotEmpty($chart[1]['payments'], 'USD payment chart should have data');
        $this->assertNotEmpty($chart[1]['expenses'], 'USD expense chart should have data');

        $summary = $this->cs->analytics_summary($this->startDate, $this->endDate);

        $this->assertNotEmpty($summary[1]['payment_delay'], 'USD payment_delay chart should have data');
        $this->assertNotEmpty($summary[1]['late_payment_rate'], 'USD late_payment_rate chart should have data');
        $this->assertNotEmpty($summary[1]['mrr'], 'USD MRR chart should have data');
    }

    // =======================================================================
    // PAYMENTABLES TIMESTAMPS BACKDATED
    // =======================================================================

    public function testPaymentablesTimestampsAreBackdated(): void
    {
        $payments = Payment::where('company_id', $this->analyticsCompany->id)
            ->where('status_id', 4)
            ->get();

        $this->assertCount(18, $payments, '18 completed payments');

        foreach ($payments as $payment) {
            $paymentable = DB::table('paymentables')
                ->where('payment_id', $payment->id)
                ->where('paymentable_type', 'invoices')
                ->first();

            $this->assertNotNull($paymentable);

            $paymentDate = Carbon::parse($payment->date)->startOfDay();
            $paymentableCreated = Carbon::parse($paymentable->created_at)->startOfDay();

            $this->assertTrue(
                $paymentDate->equalTo($paymentableCreated),
                "Paymentable created_at ({$paymentable->created_at}) must match payment date ({$payment->date})"
            );
        }
    }

    // =======================================================================
    // AGING BUCKETS
    // =======================================================================

    public function testAgingBucketsHaveData(): void
    {
        $totals = $this->cs->analytics_totals($this->startDate, $this->endDate);
        $aging = $totals[999]['aging'];

        $totalAging = (float) ($aging->current_amount ?? 0)
            + (float) ($aging->age_0_30 ?? 0)
            + (float) ($aging->age_31_60 ?? 0)
            + (float) ($aging->age_61_90 ?? 0)
            + (float) ($aging->age_91_120 ?? 0)
            + (float) ($aging->age_120_plus ?? 0);

        $this->assertGreaterThan(0, $totalAging, 'Aging total must be > 0');
    }

    /**
     * The aging response now includes a `total` field with the sum of all buckets.
     * This must match the outstanding total from the main totals() endpoint.
     */
    public function testAgingTotalMatchesOutstandingTotal(): void
    {
        $analyticsTotals = $this->cs->analytics_totals($this->startDate, $this->endDate);
        $mainTotals = $this->cs->totals($this->startDate, $this->endDate);

        // Aggregate (key 999)
        $aging = $analyticsTotals[999]['aging'];
        $this->assertObjectHasProperty('total', $aging, 'Aging must have a total field');
        $this->assertObjectHasProperty('outstanding_count', $aging, 'Aging must have an outstanding_count field');

        $agingTotal = (float) $aging->total;
        $outstanding = (float) ($mainTotals[999]['outstanding']->amount ?? 0);

        $this->assertEqualsWithDelta($outstanding, $agingTotal, 0.01,
            "Aging total (\${$agingTotal}) must match outstanding (\${$outstanding})");
        $this->assertGreaterThan(0, $agingTotal, 'Aging total must be > $0');
        $this->assertGreaterThan(0, (int) $aging->outstanding_count, 'Outstanding count must be > 0');

        // Verify total = sum of all buckets
        $bucketSum = (float) ($aging->current_amount ?? 0)
            + (float) ($aging->age_0_30 ?? 0)
            + (float) ($aging->age_31_60 ?? 0)
            + (float) ($aging->age_61_90 ?? 0)
            + (float) ($aging->age_91_120 ?? 0)
            + (float) ($aging->age_120_plus ?? 0);
        $this->assertEqualsWithDelta($agingTotal, $bucketSum, 0.01,
            'Aging total must equal sum of all aging buckets');

        // Per-currency (key 1 = USD)
        $usdAging = $analyticsTotals[1]['aging'];
        $this->assertObjectHasProperty('total', $usdAging, 'USD aging must have a total field');
        $usdAgingTotal = (float) $usdAging->total;
        $usdOutstanding = (float) ($mainTotals[1]['outstanding']->amount ?? 0);

        $this->assertEqualsWithDelta($usdOutstanding, $usdAgingTotal, 0.01,
            'USD aging total must match USD outstanding');
        $this->assertGreaterThan(0, $usdAgingTotal, 'USD aging total must be > $0');
    }

    /**
     * Verify the aging response encodes to JSON with all expected fields
     * and that values are numeric when cast (frontend sums them).
     */
    public function testAgingResponseJsonStructure(): void
    {
        $analyticsTotals = $this->cs->analytics_totals($this->startDate, $this->endDate);

        // Encode to JSON and back, mimicking the API response
        $json = json_encode($analyticsTotals);
        $decoded = json_decode($json);

        // Check key 999 (ALL)
        $this->assertObjectHasProperty('aging', $decoded->{'999'}, 'Key 999 must have aging');
        $aging999 = $decoded->{'999'}->aging;

        $requiredFields = ['total', 'current_amount', 'age_0_30', 'age_31_60', 'age_61_90', 'age_91_120', 'age_120_plus', 'outstanding_count'];
        foreach ($requiredFields as $field) {
            $this->assertObjectHasProperty($field, $aging999, "Aging must have field: {$field}");
            $this->assertTrue(is_numeric($aging999->$field), "Aging field {$field} must be numeric, got: {$aging999->$field}");
        }

        // Sum must be > 0
        $sum = 0;
        foreach ($requiredFields as $field) {
            $sum += (float) $aging999->$field;
        }
        $this->assertGreaterThan(0, $sum, 'Sum of aging fields from JSON must be > $0');

        // Check key 1 (USD)
        $this->assertObjectHasProperty('aging', $decoded->{'1'}, 'Key 1 must have aging');
        $aging1 = $decoded->{'1'}->aging;
        foreach ($requiredFields as $field) {
            $this->assertObjectHasProperty($field, $aging1, "USD aging must have field: {$field}");
            $this->assertTrue(is_numeric($aging1->$field), "USD aging field {$field} must be numeric");
        }
    }

    public function testAgingBucketsCurrencyParity(): void
    {
        $totals = $this->cs->analytics_totals($this->startDate, $this->endDate);

        $usdAging = $totals[1]['aging'];
        $allAging = $totals[999]['aging'];

        $fields = ['current_amount', 'age_0_30', 'age_31_60', 'age_61_90', 'age_91_120', 'age_120_plus'];
        foreach ($fields as $field) {
            $this->assertEqualsWithDelta(
                (float) ($usdAging->$field ?? 0),
                (float) ($allAging->$field ?? 0),
                0.01,
                "USD and ALL aging {$field} must match"
            );
        }
    }

    // =======================================================================
    // PAYMENTS — DATES IN CORRECT YEAR
    // =======================================================================

    public function testAllPaymentDatesAreInCurrentYear(): void
    {
        $year = now()->year;
        $payments = Payment::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        foreach ($payments as $payment) {
            $paymentYear = Carbon::parse($payment->date)->year;
            $this->assertEquals($year, $paymentYear,
                "Payment {$payment->number} date {$payment->date} should be in {$year}");
        }
    }

    public function testAllInvoiceDatesAreInCurrentYear(): void
    {
        $year = now()->year;
        $invoices = Invoice::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->get();

        foreach ($invoices as $invoice) {
            $invoiceYear = Carbon::parse($invoice->date)->year;
            $this->assertEquals($year, $invoiceYear,
                "Invoice {$invoice->number} date {$invoice->date} should be in {$year}");
        }
    }

    // =======================================================================
    // CLIENT PAYMENT ANALYTICS
    // =======================================================================

    public function testClientPaymentAnalyticsEndpoint(): void
    {
        $cpa = $this->cs->client_payment_analytics();

        $this->assertArrayHasKey('clients', $cpa);
        $this->assertArrayHasKey('company_summary', $cpa);

        $this->assertNotEmpty($cpa['clients'], 'Should have per-client payment analytics');
        $this->assertNotEmpty((array) $cpa['company_summary'], 'Company summary should not be empty');
    }

    // =======================================================================
    // INVOICES LINKED TO PROJECTS
    // =======================================================================

    public function testSomeInvoicesLinkedToProjects(): void
    {
        $linkedCount = Invoice::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->whereNotNull('project_id')
            ->count();

        $this->assertGreaterThan(0, $linkedCount, 'Some invoices should be linked to projects');
    }

    public function testSomeExpensesLinkedToProjects(): void
    {
        $linkedCount = Expense::where('company_id', $this->analyticsCompany->id)
            ->where('is_deleted', false)
            ->whereNotNull('project_id')
            ->count();

        $this->assertGreaterThan(0, $linkedCount, 'Some expenses should be linked to projects');
    }
}
