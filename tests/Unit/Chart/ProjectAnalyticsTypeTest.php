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
use Tests\MockAccountData;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\Chart\ChartService;
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
}
