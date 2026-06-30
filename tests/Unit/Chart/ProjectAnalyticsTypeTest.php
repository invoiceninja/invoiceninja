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

        foreach ([
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
            'recent_activity',
        ] as $key) {
            $this->assertArrayHasKey($key, $result);
        }

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
        $this->assertEqualsWithDelta(150.0, $invoiceProgress->invoiced_amount, 0.01);
        $this->assertEqualsWithDelta(50.0, $invoiceProgress->paid_amount, 0.01);
        $this->assertEqualsWithDelta(100.0, $invoiceProgress->outstanding_amount, 0.01);

        $forecast = collect($result['forecast_completion'])->firstWhere('project_id', $project->hashed_id);
        $this->assertEqualsWithDelta(2.0, $forecast->average_daily_velocity, 0.01);
        $this->assertSame('2026-01-13', $forecast->forecast_finish_date);

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
