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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Services\Chart\ProjectBurnUpService;
use App\Services\Chart\ChartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ProjectBurnUpTest extends TestCase
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
        $settings->timezone_id = '34';
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
    }

    public function testProjectBurnUpBuildsCumulativeTimeInvoicePaidAndExpenseSeries(): void
    {
        $project = $this->createProject();

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-01 09:00:00')->timestamp, Carbon::parse('2026-01-01 11:00:00')->timestamp, '', true],
                [Carbon::parse('2026-01-02 09:00:00')->timestamp, Carbon::parse('2026-01-02 10:00:00')->timestamp, '', false],
            ]),
            'duration' => 10800,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-01',
        ]);

        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 500,
            'paid_to_date' => 200,
            'balance' => 300,
            'status_id' => Invoice::STATUS_PARTIAL,
            'date' => '2026-01-02',
            'is_deleted' => false,
            'exchange_rate' => 1,
        ]);

        Expense::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 50,
            'foreign_amount' => 0,
            'date' => '2026-01-03',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 0,
            'tax_amount3' => 0,
            'uses_inclusive_taxes' => true,
            'is_deleted' => false,
        ]);

        $result = $this->burnUpService()->generate($project, '2026-01-01', '2026-01-03', 'daily');

        $this->assertSame('daily', $result['bucket_type']);
        $this->assertCount(3, $result['series']);
        $this->assertEqualsWithDelta(3.0, $result['totals']['logged_hours'], 0.01);
        $this->assertEqualsWithDelta(2.0, $result['totals']['billable_hours'], 0.01);
        $this->assertEqualsWithDelta(200.0, $result['totals']['task_value'], 0.01);
        $this->assertEqualsWithDelta(500.0, $result['totals']['invoiced_amount'], 0.01);
        $this->assertEqualsWithDelta(200.0, $result['totals']['paid_to_date'], 0.01);
        $this->assertEqualsWithDelta(300.0, $result['totals']['outstanding_amount'], 0.01);
        $this->assertEqualsWithDelta(50.0, $result['totals']['expense_amount'], 0.01);
        $this->assertEqualsWithDelta(450.0, $result['totals']['net_invoiced_amount'], 0.01);
        $this->assertEqualsWithDelta(150.0, $result['totals']['net_paid_amount'], 0.01);

        $jan2 = $result['series'][1];
        $this->assertEqualsWithDelta(500.0, $jan2['invoiced_amount'], 0.01);
        $this->assertEqualsWithDelta(200.0, $jan2['paid_to_date'], 0.01);
        $this->assertEqualsWithDelta(500.0, $jan2['cumulative_invoiced_amount'], 0.01);
        $this->assertEqualsWithDelta(200.0, $jan2['cumulative_paid_to_date'], 0.01);
    }

    public function testProjectBurnUpExcludesDraftInvoicesByDefault(): void
    {
        $project = $this->createProject();

        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 500,
            'paid_to_date' => 0,
            'status_id' => Invoice::STATUS_SENT,
            'date' => '2026-01-02',
            'is_deleted' => false,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 900,
            'paid_to_date' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
            'date' => '2026-01-02',
            'is_deleted' => false,
        ]);

        $without_drafts = $this->burnUpService(include_drafts: false)
            ->generate($project, '2026-01-01', '2026-01-03');

        $with_drafts = $this->burnUpService(include_drafts: true)
            ->generate($project, '2026-01-01', '2026-01-03');

        $this->assertEqualsWithDelta(500.0, $without_drafts['totals']['invoiced_amount'], 0.01);
        $this->assertEqualsWithDelta(1400.0, $with_drafts['totals']['invoiced_amount'], 0.01);
    }

    public function testNonAdminProjectBurnUpOmitsFinancialFields(): void
    {
        $project = $this->createProject();

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'rate' => 100,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-01 09:00:00')->timestamp, Carbon::parse('2026-01-01 11:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-01',
        ]);

        $result = $this->burnUpService(is_admin: false)
            ->generate($project, '2026-01-01', '2026-01-03', 'daily');

        $this->assertFalse($result['metadata']['can_view_financials']);
        $this->assertSame($project->hashed_id, $result['project']['id']);
        $this->assertEqualsWithDelta(10.0, $result['project']['budgeted_hours'], 0.01);
        $this->assertEqualsWithDelta(10.0, $result['markers']['budgeted_hours'], 0.01);
        $this->assertEqualsWithDelta(2.0, $result['totals']['logged_hours'], 0.01);
        $this->assertEqualsWithDelta(2.0, $result['totals']['billable_hours'], 0.01);
        $this->assertEqualsWithDelta(2.0, $result['series'][0]['cumulative_logged_hours'], 0.01);
        $this->assertArrayHasKey('ideal_hours', $result['series'][0]);
        $this->assertArrayHasKey('task_log_count', $result['series'][0]);
        $this->assertArrayHasKey('invoice_count', $result['series'][0]);
        $this->assertArrayHasKey('expense_count', $result['series'][0]);

        foreach (['task_rate', 'budgeted_amount', 'currency_id'] as $field) {
            $this->assertArrayNotHasKey($field, $result['project']);
        }

        $this->assertArrayNotHasKey('budgeted_amount', $result['markers']);

        $financial_fields = [
            'task_value',
            'invoiced_amount',
            'paid_to_date',
            'outstanding_amount',
            'expense_amount',
            'net_invoiced_amount',
            'net_paid_amount',
            'budgeted_amount',
            'ideal_amount',
        ];

        foreach ($result['series'] as $bucket) {
            foreach ($financial_fields as $field) {
                $this->assertArrayNotHasKey($field, $bucket);
                $this->assertArrayNotHasKey("cumulative_{$field}", $bucket);
            }
        }

        foreach ($financial_fields as $field) {
            $this->assertArrayNotHasKey($field, $result['totals']);
            $this->assertArrayNotHasKey("cumulative_{$field}", $result['totals']);
        }
    }

    public function testServiceSplitsTaskLogsAcrossDailyBuckets(): void
    {
        $project = $this->createProject();

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'client_id' => $this->test_client->id,
            'project_id' => $project->id,
            'rate' => 80,
            'time_log' => json_encode([
                [Carbon::parse('2026-01-01 23:00:00')->timestamp, Carbon::parse('2026-01-02 01:00:00')->timestamp, '', true],
            ]),
            'duration' => 7200,
            'is_deleted' => false,
            'is_running' => false,
            'calculated_start_date' => '2026-01-01',
        ]);

        $result = $this->burnUpService()->generate($project, '2026-01-01', '2026-01-02', 'daily');

        $this->assertCount(2, $result['series']);
        $this->assertEqualsWithDelta(1.0, $result['series'][0]['logged_hours'], 0.01);
        $this->assertEqualsWithDelta(1.0, $result['series'][1]['logged_hours'], 0.01);
        $this->assertEqualsWithDelta(80.0, $result['series'][0]['task_value'], 0.01);
        $this->assertEqualsWithDelta(80.0, $result['series'][1]['task_value'], 0.01);
        $this->assertEqualsWithDelta(2.0, $result['series'][1]['cumulative_logged_hours'], 0.01);
        $this->assertEqualsWithDelta(160.0, $result['totals']['task_value'], 0.01);
    }

    public function testServiceBuildsWeeklyBucketsAndBudgetMarkers(): void
    {
        $project = $this->createProject();

        Invoice::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 300,
            'paid_to_date' => 100,
            'status_id' => Invoice::STATUS_PARTIAL,
            'date' => '2026-01-04',
            'is_deleted' => false,
        ]);

        Expense::factory()->create([
            'client_id' => $this->test_client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'project_id' => $project->id,
            'amount' => 100,
            'foreign_amount' => 0,
            'date' => '2026-01-05',
            'tax_rate1' => 10,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_amount1' => 0,
            'tax_amount2' => 5,
            'tax_amount3' => 0,
            'uses_inclusive_taxes' => false,
            'is_deleted' => false,
        ]);

        $result = $this->burnUpService()->generate($project, '2026-01-01', '2026-01-14', 'weekly');

        $this->assertSame('weekly', $result['bucket_type']);
        $this->assertMatchesRegularExpression('/^\d{4}-W\d{2}$/', $result['series'][0]['period']);
        $this->assertEqualsWithDelta(10.0, $result['markers']['budgeted_hours'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $result['markers']['budgeted_amount'], 0.01);
        $this->assertSame('2026-01-05', $result['markers']['due_date']);
        $this->assertEqualsWithDelta(300.0, $result['series'][0]['invoiced_amount'], 0.01);
        $this->assertEqualsWithDelta(100.0, $result['series'][0]['paid_to_date'], 0.01);
        $this->assertEqualsWithDelta(115.0, $result['series'][1]['expense_amount'], 0.01);
        $this->assertEqualsWithDelta(185.0, $result['totals']['net_invoiced_amount'], 0.01);
        $this->assertEqualsWithDelta(-15.0, $result['totals']['net_paid_amount'], 0.01);
    }

    public function testProjectBurnUpEndpointAcceptsHashedProjectId(): void
    {
        $project = $this->createProject($this->company, $this->client);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'amount' => 125,
            'paid_to_date' => 75,
            'status_id' => Invoice::STATUS_PARTIAL,
            'date' => '2026-01-02',
            'is_deleted' => false,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/charts/project_burnup/{$project->hashed_id}", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-03',
        ]);

        $response->assertStatus(200);
        $this->assertSame($project->hashed_id, $response->json('project.id'));
        $this->assertSame('daily', $response->json('bucket_type'));
        $this->assertEqualsWithDelta(125.0, $response->json('totals.invoiced_amount'), 0.01);
        $this->assertEqualsWithDelta(75.0, $response->json('totals.paid_to_date'), 0.01);
    }

    public function testProjectBurnUpEndpointRejectsRawProjectId(): void
    {
        $project = $this->createProject($this->company, $this->client);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/charts/project_burnup/{$project->id}", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-03',
        ]);

        $response->assertStatus(404);
    }

    public function testProjectBurnUpEndpointRejectsCrossCompanyProject(): void
    {
        $project = $this->createProject($this->test_company, $this->test_client);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/charts/project_burnup/{$project->hashed_id}", [
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-03',
        ]);

        $response->assertStatus(403);
    }

    public function testProjectBurnUpPrefersBudgetedAmountColumn(): void
    {
        $project = $this->createProject();
        $project->budgeted_amount = 2500;
        $project->save();

        $result = $this->burnUpService()->generate($project->fresh(), '2026-01-01', '2026-01-05', 'daily');

        $this->assertEqualsWithDelta(2500.0, $result['project']['budgeted_amount'], 0.01);
        $this->assertEqualsWithDelta(2500.0, $result['markers']['budgeted_amount'], 0.01);
        $this->assertEqualsWithDelta(2500.0, $result['series'][0]['budgeted_amount'], 0.01);
        $this->assertEqualsWithDelta(2000.0, $result['series'][4]['ideal_amount'], 0.01);
    }

    private function createProject(?Company $company = null, ?Client $client = null): Project
    {
        $company ??= $this->test_company;
        $client ??= $this->test_client;

        return Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Burn Up Project',
            'budgeted_hours' => 10,
            'current_hours' => 0,
            'task_rate' => 100,
            'due_date' => '2026-01-05',
            'is_deleted' => false,
            'created_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function burnUpService(bool $is_admin = true, bool $include_drafts = false): ProjectBurnUpService
    {
        return new ProjectBurnUpService($this->test_company, $this->user, $is_admin, $include_drafts);
    }
}
