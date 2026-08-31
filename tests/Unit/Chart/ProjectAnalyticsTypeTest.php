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
use App\Models\Task;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\TaskStatus;
use App\Models\User;
use Tests\MockAccountData;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\Chart\ChartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ProjectAnalyticsTypeTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private Company $test_company;
    private Client $test_client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $settings = CompanySettings::defaults();
        $settings->currency_id = '1';
        $settings->country_id = '840';

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
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testBudgetSummaryReturnsNumericTypes(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'budgeted_hours' => 100,
            'current_hours' => 40,
            'task_rate' => 150.00,
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'is_running' => true,
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'is_running' => false,
        ]);

        $cs = new ChartService($this->test_company, $this->user, true);
        $result = $cs->project_analytics();

        $this->assertArrayHasKey('budget_summary', $result);
        $this->assertNotEmpty($result['budget_summary']);

        $row = $result['budget_summary'][0];

        $this->assertIsFloat($row->budgeted_hours);
        $this->assertIsFloat($row->current_hours);
        $this->assertIsFloat($row->task_rate);
        $this->assertIsInt($row->total_tasks);
        $this->assertIsInt($row->invoiced_tasks);
        $this->assertIsInt($row->uninvoiced_tasks);
        $this->assertIsInt($row->running_tasks);
        $this->assertIsFloat($row->utilization);
        $this->assertIsFloat($row->hours_remaining);
        $this->assertIsString($row->currency_id);

        $this->assertEquals(2, $row->total_tasks);
        $this->assertEquals(1, $row->running_tasks);
        $this->assertEqualsWithDelta(0.4, $row->utilization, 0.001);
        $this->assertEqualsWithDelta(60.0, $row->hours_remaining, 0.01);
    }

    public function testProfitabilityReturnsNumericTypes(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'budgeted_hours' => 50,
        ]);

        $cs = new ChartService($this->test_company, $this->user, true);
        $result = $cs->project_analytics();

        $this->assertArrayHasKey('profitability', $result);
        $this->assertNotEmpty($result['profitability']);

        $row = $result['profitability'][0];

        $this->assertIsFloat($row->invoiced_amount);
        $this->assertIsFloat($row->expense_amount);
        $this->assertIsFloat($row->net_margin);
        $this->assertIsFloat($row->margin_ratio);
        $this->assertIsString($row->currency_id);
    }

    public function testRunningTasksSumCorrectlyAsIntegers(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $project = Project::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->test_company->id,
                'client_id' => $this->test_client->id,
                'budgeted_hours' => 10,
            ]);

            Task::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->test_company->id,
                'project_id' => $project->id,
                'is_running' => false,
            ]);
        }

        $cs = new ChartService($this->test_company, $this->user, true);
        $result = $cs->project_analytics();

        $totalRunning = array_sum(array_map(fn ($r) => $r->running_tasks, $result['budget_summary']));

        $this->assertIsInt($totalRunning);
        $this->assertEquals(0, $totalRunning);
        $this->assertNotSame('0000000', (string) $totalRunning);
    }

    public function testProjectAnalyticsIncludesVisualizationDatasets(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 00:00:00'));

        $doneStatus = TaskStatus::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'name' => 'Done',
            'status_order' => 4,
            'is_deleted' => false,
        ]);

        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'name' => 'Visualization Project',
            'budgeted_hours' => 10,
            'current_hours' => 4,
            'task_rate' => 100,
            'due_date' => '2026-01-20',
            'is_deleted' => false,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'status_id' => $doneStatus->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-02 09:00:00')->timestamp, Carbon::parse('2026-01-02 11:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-02',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-03 09:00:00')->timestamp, Carbon::parse('2026-01-03 11:00:00')->timestamp, '', false],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-03',
        ]);

        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 150,
            'paid_to_date' => 50,
            'balance' => 100,
            'status_id' => Invoice::STATUS_PARTIAL,
            'date' => '2026-01-04',
            'is_deleted' => false,
        ]);

        Expense::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 50,
            'foreign_amount' => 0,
            'date' => '2026-01-04',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
            'uses_inclusive_taxes' => true,
            'is_deleted' => false,
        ]);

        $result = (new ChartService($this->test_company, $this->user, true))->project_analytics();

        $this->assertSame([
            'budget_summary',
            'budget_vs_actual',
            'estimated_vs_logged_hours',
            'invoice_progress',
            'forecast_completion',
            'project_health',
            'team_contribution',
            'time_distribution',
            'unbilled_hours',
            'velocity_trend',
            'timeline_variance',
            'expense_breakdown',
            'cumulative_spend',
            'profitability',
            'metadata',
        ], array_keys($result));

        $budget = collect($result['budget_vs_actual'])->firstWhere('project_id', $project->hashed_id);
        $this->assertNotNull($budget);
        $this->assertIsString($budget->project_id);
        $this->assertEqualsWithDelta(1000.0, $budget->budgeted_amount, 0.01);
        $this->assertEqualsWithDelta(250.0, $budget->actual_amount, 0.01);
        $this->assertEqualsWithDelta(0.25, $budget->budget_utilization, 0.001);

        $effort = collect($result['estimated_vs_logged_hours'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(10.0, $effort->estimated_hours, 0.01);
        $this->assertEqualsWithDelta(4.0, $effort->logged_hours, 0.01);
        $this->assertEqualsWithDelta(6.0, $effort->remaining_hours, 0.01);

        $invoiceProgress = collect($result['invoice_progress'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(200.0, $invoiceProgress->work_value, 0.01);
        $this->assertEqualsWithDelta(150.0, $invoiceProgress->invoiced_amount, 0.01);
        $this->assertEqualsWithDelta(50.0, $invoiceProgress->paid_amount, 0.01);
        $this->assertEqualsWithDelta(100.0, $invoiceProgress->outstanding_amount, 0.01);
        $this->assertEqualsWithDelta(50.0, $invoiceProgress->unbilled_amount, 0.01);
        $this->assertEqualsWithDelta(0.75, $invoiceProgress->invoice_progress, 0.001);

        $unbilledHours = collect($result['unbilled_hours'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(50.0, $unbilledHours->unbilled_amount, 0.01);

        $forecast = collect($result['forecast_completion'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(2.0, $forecast->average_daily_velocity, 0.01);
        $this->assertSame('2026-01-13', $forecast->forecast_finish_date);
        $this->assertSame(7, $forecast->schedule_variance_days);

        $timelineVariance = collect($result['timeline_variance'])->firstWhere('project_id', $project->hashed_id);
        $this->assertSame(7, $timelineVariance->schedule_variance_days);

        $teamContribution = collect($result['team_contribution'])->firstWhere('project_id', $project->hashed_id);
        $this->assertCount(1, $teamContribution->team_contribution);
        $this->assertIsString($teamContribution->team_contribution[0]['user_id']);
        $this->assertEqualsWithDelta(4.0, $teamContribution->team_contribution[0]['logged_hours'], 0.01);

        $timeDistribution = collect($result['time_distribution'])->firstWhere('project_id', $project->hashed_id);
        $this->assertIsString($timeDistribution->time_distribution[0]['task_id']);

        $cumulativeSpend = collect($result['cumulative_spend'])->firstWhere('project_id', $project->hashed_id);
        $this->assertNotEmpty($cumulativeSpend->cumulative_spend);

        $profitability = collect($result['profitability'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(100.0, $profitability->net_margin, 0.01);
    }

    public function testScheduleVarianceIsNegativeWhenProjectIsBehind(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 00:00:00'));

        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'name' => 'Behind Schedule Project',
            'budgeted_hours' => 10,
            'current_hours' => 4,
            'task_rate' => 100,
            'due_date' => '2026-01-10',
            'is_deleted' => false,
            'created_at' => '2026-01-01 00:00:00',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-02 09:00:00')->timestamp, Carbon::parse('2026-01-02 11:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-02',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-03 09:00:00')->timestamp, Carbon::parse('2026-01-03 11:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-03',
        ]);

        $result = (new ChartService($this->test_company, $this->user, true))->project_analytics();

        $forecast = collect($result['forecast_completion'])->firstWhere('project_id', $project->hashed_id);
        $this->assertSame('2026-01-13', $forecast->forecast_finish_date);
        $this->assertSame(-3, $forecast->schedule_variance_days);

        $timelineVariance = collect($result['timeline_variance'])->firstWhere('project_id', $project->hashed_id);
        $this->assertSame(-3, $timelineVariance->schedule_variance_days);

        $projectHealth = collect($result['project_health'])->firstWhere('project_id', $project->hashed_id);
        $this->assertSame(-3, $projectHealth->indicators['schedule_variance_days']);
    }

    public function testProjectAnalyticsPrefersBudgetedAmountColumn(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'name' => 'Fixed Budget Project',
            'budgeted_hours' => 10,
            'budgeted_amount' => 1500,
            'current_hours' => 1,
            'task_rate' => 100,
            'is_deleted' => false,
        ]);

        $result = (new ChartService($this->test_company, $this->user, true))->project_analytics();

        $budget = collect($result['budget_vs_actual'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(1500.0, $budget->budgeted_amount, 0.01);
        $this->assertEqualsWithDelta(100.0, $budget->actual_amount, 0.01);
        $this->assertEqualsWithDelta(1400.0, $budget->remaining_budget, 0.01);
        $this->assertEqualsWithDelta(0.0667, $budget->budget_utilization, 0.0001);

        $summary = collect($result['budget_summary'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(1500.0, $summary->budgeted_amount, 0.01);
    }

    public function testTaskInvoicedProjectsUseTaskLinksForUnbilledAmount(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'name' => 'Task Invoiced Project',
            'budgeted_hours' => 10,
            'current_hours' => 5,
            'task_rate' => 100,
            'is_deleted' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => null,
            'amount' => 200,
            'paid_to_date' => 50,
            'balance' => 150,
            'status_id' => Invoice::STATUS_PARTIAL,
            'date' => '2026-01-04',
            'is_deleted' => false,
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'invoice_id' => $invoice->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-02 09:00:00')->timestamp, Carbon::parse('2026-01-02 11:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-02',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-03 09:00:00')->timestamp, Carbon::parse('2026-01-03 12:00:00')->timestamp, '', true],
            ]),
            'duration' => 10800,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-03',
        ]);

        $result = (new ChartService($this->test_company, $this->user, true))->project_analytics();

        $invoiceProgress = collect($result['invoice_progress'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(500.0, $invoiceProgress->work_value, 0.01);
        $this->assertEqualsWithDelta(200.0, $invoiceProgress->invoiced_amount, 0.01);
        $this->assertEqualsWithDelta(50.0, $invoiceProgress->paid_amount, 0.01);
        $this->assertEqualsWithDelta(150.0, $invoiceProgress->outstanding_amount, 0.01);
        $this->assertEqualsWithDelta(300.0, $invoiceProgress->unbilled_amount, 0.01);
        $this->assertEqualsWithDelta(0.4, $invoiceProgress->invoice_progress, 0.001);

        $unbilledHours = collect($result['unbilled_hours'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(3.0, $unbilledHours->unbilled_hours, 0.01);
        $this->assertEqualsWithDelta(300.0, $unbilledHours->unbilled_amount, 0.01);
        $this->assertSame(1, $unbilledHours->uninvoiced_tasks);
    }

    public function testProjectInvoicesUseProjectTotalsEvenWhenTasksAreLinked(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'name' => 'Project Invoice With Linked Tasks',
            'budgeted_hours' => 10,
            'current_hours' => 4,
            'task_rate' => 100,
            'is_deleted' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 150,
            'paid_to_date' => 0,
            'balance' => 150,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-01-04',
            'is_deleted' => false,
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'invoice_id' => $invoice->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-02 09:00:00')->timestamp, Carbon::parse('2026-01-02 11:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-02',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'invoice_id' => $invoice->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-03 09:00:00')->timestamp, Carbon::parse('2026-01-03 11:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-03',
        ]);

        $result = (new ChartService($this->test_company, $this->user, true))->project_analytics();

        $invoiceProgress = collect($result['invoice_progress'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(400.0, $invoiceProgress->work_value, 0.01);
        $this->assertEqualsWithDelta(150.0, $invoiceProgress->invoiced_amount, 0.01);
        $this->assertEqualsWithDelta(250.0, $invoiceProgress->unbilled_amount, 0.01);

        $unbilledHours = collect($result['unbilled_hours'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(0.0, $unbilledHours->unbilled_hours, 0.01);
        $this->assertEqualsWithDelta(250.0, $unbilledHours->unbilled_amount, 0.01);
    }

    public function testTaskInvoiceSharedAcrossProjectsIsAllocatedByLinkedTaskValue(): void
    {
        $firstProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'name' => 'Shared Task Invoice A',
            'budgeted_hours' => 10,
            'current_hours' => 2,
            'task_rate' => 100,
            'is_deleted' => false,
        ]);

        $secondProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'name' => 'Shared Task Invoice B',
            'budgeted_hours' => 10,
            'current_hours' => 3,
            'task_rate' => 100,
            'is_deleted' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => null,
            'amount' => 500,
            'paid_to_date' => 100,
            'balance' => 400,
            'status_id' => Invoice::STATUS_PARTIAL,
            'date' => '2026-01-04',
            'is_deleted' => false,
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $firstProject->id,
            'invoice_id' => $invoice->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-02 09:00:00')->timestamp, Carbon::parse('2026-01-02 11:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-02',
        ]);

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $secondProject->id,
            'invoice_id' => $invoice->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-03 09:00:00')->timestamp, Carbon::parse('2026-01-03 12:00:00')->timestamp, '', true],
            ]),
            'duration' => 10800,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-03',
        ]);

        $result = (new ChartService($this->test_company, $this->user, true))->project_analytics();

        $firstProgress = collect($result['invoice_progress'])->firstWhere('project_id', $firstProject->hashed_id);
        $this->assertEqualsWithDelta(200.0, $firstProgress->work_value, 0.01);
        $this->assertEqualsWithDelta(200.0, $firstProgress->invoiced_amount, 0.01);
        $this->assertEqualsWithDelta(40.0, $firstProgress->paid_amount, 0.01);
        $this->assertEqualsWithDelta(160.0, $firstProgress->outstanding_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, $firstProgress->unbilled_amount, 0.01);

        $secondProgress = collect($result['invoice_progress'])->firstWhere('project_id', $secondProject->hashed_id);
        $this->assertEqualsWithDelta(300.0, $secondProgress->work_value, 0.01);
        $this->assertEqualsWithDelta(300.0, $secondProgress->invoiced_amount, 0.01);
        $this->assertEqualsWithDelta(60.0, $secondProgress->paid_amount, 0.01);
        $this->assertEqualsWithDelta(240.0, $secondProgress->outstanding_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, $secondProgress->unbilled_amount, 0.01);
    }

    public function testProjectAnalyticsEndpointReturnsVisualizationData(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Endpoint Project',
            'budgeted_hours' => 5,
            'current_hours' => 1,
            'task_rate' => 100,
            'is_deleted' => false,
        ]);

        $otherProject = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'name' => 'Other Endpoint Project',
            'budgeted_hours' => 5,
            'current_hours' => 1,
            'task_rate' => 100,
            'is_deleted' => false,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'amount' => 99,
            'paid_to_date' => 0,
            'balance' => 99,
            'status_id' => Invoice::STATUS_DRAFT,
            'date' => '2026-01-04',
            'is_deleted' => false,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/charts/project_analytics/{$project->hashed_id}", [
            'include_drafts' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'budget_summary',
                'budget_vs_actual',
                'invoice_progress',
                'forecast_completion',
                'project_health',
                'profitability',
                'metadata',
            ]);

        $this->assertTrue($response->json('metadata.include_drafts'));

        $invoiceProgress = collect($response->json('invoice_progress'))->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(99.0, $invoiceProgress['invoiced_amount'], 0.01);
        $this->assertSame(1, $response->json('metadata.project_count'));
        $this->assertNull(collect($response->json('budget_summary'))->firstWhere('project_id', $otherProject->hashed_id));
    }

    public function testNonAdminProjectAnalyticsIncludesExplicitlyAuthorizedProjectNotOwnedByUser(): void
    {
        $projectOwner = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'project-owner@gmail.com',
        ]);

        $project = Project::factory()->create([
            'user_id' => $projectOwner->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'name' => 'Assigned Project',
            'budgeted_hours' => 5,
            'current_hours' => 1,
            'task_rate' => 100,
            'is_deleted' => false,
        ]);

        $result = (new ChartService($this->test_company, $this->user, false))
            ->project_analytics($project);

        $this->assertFalse($result['metadata']['can_view_financials']);
        $this->assertSame(1, $result['metadata']['project_count']);
        $this->assertNotEmpty($result['forecast_completion']);
        $this->assertSame($project->hashed_id, $result['forecast_completion'][0]->project_id);

        foreach ($result as $dataset => $rows) {
            if (in_array($dataset, ['forecast_completion', 'metadata'], true)) {
                continue;
            }

            $this->assertSame([], $rows, "{$dataset} should not contain non-admin datapoints.");
        }
    }

    public function testProjectAnalyticsEndpointRejectsRawProjectId(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'is_deleted' => false,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/charts/project_analytics/{$project->id}");

        $response->assertStatus(404);
    }

    public function testProjectAnalyticsEndpointRejectsCrossCompanyProject(): void
    {
        $otherCompany = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $otherClient = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $otherCompany->id,
        ]);

        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $otherCompany->id,
            'client_id' => $otherClient->id,
            'is_deleted' => false,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/charts/project_analytics/{$project->hashed_id}");

        $response->assertStatus(403);
    }
}
