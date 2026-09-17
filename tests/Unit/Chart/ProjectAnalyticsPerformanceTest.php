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
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Chart\ChartService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\MockAccountData;
use Tests\TestCase;

class ProjectAnalyticsPerformanceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private Company $test_company;
    private Client $test_client;

    /** @var array<int, User> */
    private array $analyticsUsers = [];

    /** @var array<int, Client> */
    private array $analyticsClients = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-01-15 00:00:00'));

        $this->makeTestData();

        $settings = CompanySettings::defaults();
        $settings->currency_id = '1';
        $settings->country_id = '840';

        $this->test_company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $clientSettings = ClientSettings::defaults();
        $clientSettings->currency_id = '1';

        $this->test_client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->test_company->id,
            'settings' => $clientSettings,
        ]);

        Model::withoutEvents(function (): void {
            $this->analyticsUsers = $this->seedUsers(8);
            $this->analyticsClients = $this->seedClients(5);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testProjectAnalyticsBenchmarksSmallAndLargeDatasets(): void
    {
        $this->seedProjectAnalyticsDataset(
            projects: 3,
            tasksPerProject: 4,
            invoicesPerProject: 2,
            expensesPerProject: 2,
        );

        [$smallResult, $smallMetrics] = $this->benchmarkProjectAnalytics('small');

        $this->assertSame(3, $smallMetrics['project_count']);
        $this->assertSame(12, $smallMetrics['task_points']);
        $this->assertLessThanOrEqual(20, $smallMetrics['query_count']);

        $this->seedProjectAnalyticsDataset(
            projects: 47,
            tasksPerProject: 10,
            invoicesPerProject: 3,
            expensesPerProject: 3,
            projectOffset: 3,
        );

        [$largeResult, $largeMetrics] = $this->benchmarkProjectAnalytics('large');

        $this->assertSame(50, $largeMetrics['project_count']);
        $this->assertSame(482, $largeMetrics['task_points']);
        $this->assertGreaterThan($smallMetrics['task_points'], $largeMetrics['task_points']);
        $this->assertGreaterThan($smallMetrics['elapsed_ms'], $largeMetrics['elapsed_ms']);
        $this->assertLessThanOrEqual(20, $largeMetrics['query_count']);
        $this->assertLessThanOrEqual($smallMetrics['query_count'] + 2, $largeMetrics['query_count']);
        $this->assertSame(50, $largeResult['metadata']['project_count']);
        $this->assertSame(3, $smallResult['metadata']['project_count']);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, int|string|float>}
     */
    private function benchmarkProjectAnalytics(string $label): array
    {
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $startMemory = memory_get_usage(true);
        $startPeakMemory = memory_get_peak_usage(true);
        $start = hrtime(true);

        $result = (new ChartService($this->test_company, $this->user, true, true))->project_analytics();

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $queries = $connection->getQueryLog();
        $connection->disableQueryLog();

        $metrics = [
            'label' => $label,
            'database' => (string) config('database.default'),
            'project_count' => count($result['budget_summary']),
            'task_points' => $this->nestedPointCount($result['time_distribution'], 'time_distribution'),
            'team_points' => $this->nestedPointCount($result['team_contribution'], 'team_contribution'),
            'velocity_points' => $this->nestedPointCount($result['velocity_trend'], 'velocity_trend'),
            'expense_points' => $this->nestedPointCount($result['expense_breakdown'], 'expense_breakdown'),
            'cumulative_spend_points' => $this->nestedPointCount($result['cumulative_spend'], 'cumulative_spend'),
            'query_count' => count($queries),
            'elapsed_ms' => round($elapsedMs, 2),
            'memory_delta_mb' => round((memory_get_usage(true) - $startMemory) / 1048576, 2),
            'peak_memory_delta_mb' => round((memory_get_peak_usage(true) - $startPeakMemory) / 1048576, 2),
        ];

        fwrite(STDOUT, "\nPROJECT_ANALYTICS_BENCHMARK " . json_encode($metrics) . "\n");

        return [$result, $metrics];
    }

    /**
     * @param array<int, \stdClass> $rows
     */
    private function nestedPointCount(array $rows, string $seriesName): int
    {
        return array_sum(array_map(
            fn (\stdClass $row): int => count($row->{$seriesName}),
            $rows
        ));
    }

    /**
     * @return array<int, User>
     */
    private function seedUsers(int $count): array
    {
        $users = [$this->user];

        for ($i = 1; $i < $count; $i++) {
            $users[] = User::factory()->create([
                'account_id' => $this->account->id,
                'first_name' => "Analytics {$i}",
                'last_name' => 'User',
                'email' => "analytics-user-{$i}@example.test",
            ]);
        }

        return $users;
    }

    /**
     * @return array<int, Client>
     */
    private function seedClients(int $count): array
    {
        $clients = [$this->test_client];

        for ($i = 1; $i < $count; $i++) {
            $settings = ClientSettings::defaults();
            $settings->currency_id = '1';

            $clients[] = Client::factory()->create([
                'user_id' => $this->analyticsUsers[$i % count($this->analyticsUsers)]->id,
                'company_id' => $this->test_company->id,
                'settings' => $settings,
                'is_deleted' => false,
            ]);
        }

        return $clients;
    }

    private function seedProjectAnalyticsDataset(
        int $projects,
        int $tasksPerProject,
        int $invoicesPerProject,
        int $expensesPerProject,
        int $projectOffset = 0,
    ): void {
        Model::withoutEvents(function () use ($projects, $tasksPerProject, $invoicesPerProject, $expensesPerProject, $projectOffset): void {
            $doneStatus = TaskStatus::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->test_company->id,
                'name' => "Done {$projectOffset}",
                'status_order' => 4,
                'is_deleted' => false,
            ]);

            $reviewStatus = TaskStatus::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->test_company->id,
                'name' => "Review {$projectOffset}",
                'status_order' => 2,
                'is_deleted' => false,
            ]);

            for ($projectIndex = 0; $projectIndex < $projects; $projectIndex++) {
                $projectNumber = $projectOffset + $projectIndex;
                $client = $this->analyticsClients[$projectNumber % count($this->analyticsClients)];
                $owner = $this->analyticsUsers[$projectNumber % count($this->analyticsUsers)];
                $taskRate = 110 + (($projectNumber % 5) * 15);
                $loggedHours = round($tasksPerProject * 1.5, 2);

                $project = Project::factory()->create([
                    'user_id' => $owner->id,
                    'company_id' => $this->test_company->id,
                    'client_id' => $client->id,
                    'name' => "Benchmark Project {$projectNumber}",
                    'budgeted_hours' => $loggedHours + 20 + ($projectNumber % 8),
                    'budgeted_amount' => $projectNumber % 2 === 0 ? ($loggedHours + 20) * $taskRate : 0,
                    'current_hours' => $loggedHours,
                    'task_rate' => $taskRate,
                    'due_date' => Carbon::parse('2026-03-01')->addDays($projectNumber % 45)->toDateString(),
                    'is_deleted' => false,
                    'created_at' => Carbon::parse('2026-01-01')->subDays($projectNumber % 10)->toDateTimeString(),
                ]);

                $invoices = $this->seedInvoicesForProject($project, $client, $owner, $invoicesPerProject, $projectNumber);

                $this->seedTasksForProject($project, $client, $doneStatus, $reviewStatus, $invoices, $tasksPerProject, $projectNumber);
                $this->seedExpensesForProject($project, $client, $owner, $expensesPerProject, $projectNumber);
            }
        });
    }

    /**
     * @return array<int, Invoice>
     */
    private function seedInvoicesForProject(Project $project, Client $client, User $owner, int $invoiceCount, int $projectNumber): array
    {
        $invoices = [];
        $statuses = [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID, Invoice::STATUS_DRAFT];

        for ($invoiceIndex = 0; $invoiceIndex < $invoiceCount; $invoiceIndex++) {
            $amount = 800 + ($projectNumber * 7) + ($invoiceIndex * 125);
            $status = $statuses[($projectNumber + $invoiceIndex) % count($statuses)];
            $paid = match ($status) {
                Invoice::STATUS_PAID => $amount,
                Invoice::STATUS_PARTIAL => $amount / 2,
                default => 0,
            };

            $date = Carbon::parse('2026-01-05')->addDays(($projectNumber + $invoiceIndex) % 40);

            $invoices[] = Invoice::factory()->create([
                'client_id' => $client->id,
                'user_id' => $owner->id,
                'company_id' => $this->test_company->id,
                'project_id' => $project->id,
                'amount' => $amount,
                'paid_to_date' => $paid,
                'balance' => max($amount - $paid, 0),
                'status_id' => $status,
                'date' => $date->toDateString(),
                'due_date' => $date->copy()->addDays(14)->toDateString(),
                'is_deleted' => false,
            ]);
        }

        return $invoices;
    }

    /**
     * @param array<int, Invoice> $invoices
     */
    private function seedTasksForProject(
        Project $project,
        Client $client,
        TaskStatus $doneStatus,
        TaskStatus $reviewStatus,
        array $invoices,
        int $taskCount,
        int $projectNumber,
    ): void {
        for ($taskIndex = 0; $taskIndex < $taskCount; $taskIndex++) {
            $taskUser = $this->analyticsUsers[($projectNumber + $taskIndex) % count($this->analyticsUsers)];
            $start = Carbon::parse('2026-01-02 09:00:00')->addDays(($projectNumber * 2 + $taskIndex) % 45);
            $end = $start->copy()->addMinutes(60 + (($taskIndex % 4) * 30));
            $invoice = $taskIndex % 4 === 0 && $invoices !== [] ? $invoices[$taskIndex % count($invoices)] : null;

            Task::factory()->create([
                'user_id' => $taskUser->id,
                'company_id' => $this->test_company->id,
                'client_id' => $client->id,
                'project_id' => $project->id,
                'invoice_id' => $invoice?->id,
                'status_id' => match ($taskIndex % 3) {
                    0 => $doneStatus->id,
                    1 => $reviewStatus->id,
                    default => null,
                },
                'number' => "TASK-{$projectNumber}-{$taskIndex}",
                'rate' => $project->task_rate,
                'time_log' => json_encode([
                    [$start->timestamp, $end->timestamp, "Benchmark task {$taskIndex}", $taskIndex % 5 !== 0],
                ]),
                'duration' => $start->diffInSeconds($end),
                'is_deleted' => false,
                'is_running' => false,
                'calculated_start_date' => $start->toDateString(),
            ]);
        }
    }

    private function seedExpensesForProject(Project $project, Client $client, User $owner, int $expenseCount, int $projectNumber): void
    {
        for ($expenseIndex = 0; $expenseIndex < $expenseCount; $expenseIndex++) {
            Expense::factory()->create([
                'client_id' => $client->id,
                'user_id' => $owner->id,
                'company_id' => $this->test_company->id,
                'project_id' => $project->id,
                'amount' => 150 + ($projectNumber * 3) + ($expenseIndex * 20),
                'foreign_amount' => 0,
                'date' => Carbon::parse('2026-01-08')->addDays(($projectNumber + $expenseIndex) % 35)->toDateString(),
                'tax_rate1' => 0,
                'tax_rate2' => 0,
                'tax_rate3' => 0,
                'tax_amount1' => 0,
                'tax_amount2' => 0,
                'tax_amount3' => 0,
                'uses_inclusive_taxes' => true,
                'is_deleted' => false,
            ]);
        }
    }
}
