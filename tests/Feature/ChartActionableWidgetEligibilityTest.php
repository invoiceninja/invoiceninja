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

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\Chart\ChartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class ChartActionableWidgetEligibilityTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private Company $metricCompany;
    private Client $metricClient;
    private TaskStatus $activeStatus;
    private ChartService $charts;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00 UTC'));
        $this->makeTestData();
        $settings = CompanySettings::defaults();
        $settings->currency_id = '1';
        $settings->timezone_id = (string) app('timezones')->firstWhere('name', 'Europe/London')->id;
        $this->metricCompany = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);
        $this->metricClient = Client::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
        ]);
        $this->activeStatus = TaskStatus::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
            'name' => 'In progress',
            'status_order' => 3,
        ]);
        $this->charts = new ChartService($this->metricCompany, $this->user, true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private static function states(): array
    {
        return [
            'archived record' => false,
            'archived client' => false,
            'deleted client' => false,
            'deleted record' => false,
            'active client' => true,
            'no client' => true,
        ];
    }

    public static function taskCases(): array
    {
        $cases = [];
        foreach ([
            'getTaskRemainingEstimatedDuration' => ['sum', 'avg'],
            'getUnestimatedTasks' => ['count'],
            'getTasksOverEstimate' => ['count'],
            'getOverdueTasks' => ['count'],
            'getTasksDue' => ['count'],
        ] as $method => $calculations) {
            foreach ($calculations as $calculation) {
                foreach (self::states() as $state => $included) {
                    $cases["$method / $calculation / $state"] = [$method, $calculation, $state, $included];
                }
            }
        }
        return $cases;
    }

    private function candidateClientId(string $state): ?int
    {
        if ($state === 'no client') {
            return null;
        }
        if (! in_array($state, ['archived client', 'deleted client'], true)) {
            return $this->metricClient->id;
        }

        $client = $this->metricClient->replicate();
        $client->save();
        if ($state === 'deleted client') {
            // Test the permanent-deletion flag independently of deleted_at.
            $client->is_deleted = true;
            $client->saveQuietly();
        } else {
            $client->delete();
        }
        return $client->id;
    }

    private function applyRecordState(Task|Expense $record, string $state): void
    {
        if ($state === 'archived record') {
            $record->delete();
        } elseif ($state === 'deleted record') {
            $record->is_deleted = true;
            $record->saveQuietly();
        }
    }

    private function createMetricTask(?int $clientId, ?int $estimate): Task
    {
        $start = Carbon::parse('2026-06-01 09:00:00 UTC')->timestamp;
        return Task::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
            'client_id' => $clientId,
            'status_id' => $this->activeStatus->id,
            'estimated_duration' => $estimate,
            'calculated_start_date' => '2026-06-01',
            'due_date' => '2026-06-14',
            'time_log' => json_encode([[$start, $start + 600, '', true]]),
            'duration' => 600,
            'is_deleted' => false,
            'is_running' => false,
        ]);
    }

    #[DataProvider('taskCases')]
    public function testActionableTasksExcludeInactiveRecordsAndClients(string $method, string $calculation, string $state, bool $included): void
    {
        $estimate = match ($method) {
            'getUnestimatedTasks' => null,
            'getTasksOverEstimate' => 100,
            default => 1800,
        };
        $this->createMetricTask($this->metricClient->id, $estimate);
        $candidate = $this->createMetricTask(
            $this->candidateClientId($state),
            $method === 'getTaskRemainingEstimatedDuration' ? 3600 : $estimate,
        );
        $this->applyRecordState($candidate, $state);

        $expected = $included ? 2 : 1;
        if ($method === 'getTaskRemainingEstimatedDuration') {
            $expected = $included ? ($calculation === 'sum' ? 4200 : 2100) : 1200;
        }

        $actual = $this->charts->$method(['period' => 'total', 'calculation' => $calculation]);
        $this->assertEquals($expected, $actual, "$method ($calculation): $state must " . ($included ? 'remain included' : 'be excluded'));
    }

    public static function expenseCases(): array
    {
        $cases = [];
        foreach (['count', 'sum', 'avg'] as $calculation) {
            foreach (self::states() as $state => $included) {
                $cases["$calculation / $state"] = [$calculation, $state, $included];
            }
        }
        return $cases;
    }

    private function createPendingExpense(?int $clientId, float $amount): Expense
    {
        return Expense::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
            'client_id' => $clientId,
            'date' => '2026-06-01',
            'amount' => $amount,
            'exchange_rate' => 1,
            'currency_id' => 1,
            'should_be_invoiced' => true,
            'invoice_id' => null,
            'is_deleted' => false,
        ]);
    }

    #[DataProvider('expenseCases')]
    public function testPendingExpensesExcludeInactiveRecordsAndClients(string $calculation, string $state, bool $included): void
    {
        $this->createPendingExpense($this->metricClient->id, 100);
        $candidate = $this->createPendingExpense($this->candidateClientId($state), 300);
        $this->applyRecordState($candidate, $state);

        $expected = match ($calculation) {
            'count' => $included ? 2 : 1,
            'sum' => $included ? 400 : 100,
            'avg' => $included ? 200 : 100,
        };
        $actual = $this->charts->getPendingExpenses([
            'period' => 'total',
            'calculation' => $calculation,
            'currency_id' => '999',
        ]);
        $this->assertEquals($expected, $actual, "getPendingExpenses ($calculation): $state must " . ($included ? 'remain included' : 'be excluded'));
    }
}
