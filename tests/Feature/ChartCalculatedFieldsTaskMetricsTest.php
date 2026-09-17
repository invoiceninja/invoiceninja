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

use App\Models\Company;
use App\Models\Task;
use App\Models\TaskStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\MockAccountData;
use Tests\TestCase;

class ChartCalculatedFieldsTaskMetricsTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private TaskStatus $activeStatus;

    private TaskStatus $completedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00 UTC'));
        $this->makeTestData();

        $this->activeStatus = TaskStatus::query()
            ->where('company_id', $this->company->id)
            ->where('status_order', 3)
            ->firstOrFail();
        $this->completedStatus = TaskStatus::query()
            ->where('company_id', $this->company->id)
            ->where('status_order', 4)
            ->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testTaskDurationFieldsReturnEstimatedAndRemainingSeconds(): void
    {
        $this->createMetricTask([
            'estimated_duration' => 7200,
            'logged_duration' => 3600,
            'calculated_start_date' => '2026-08-10',
        ]);
        $this->createMetricTask([
            'estimated_duration' => 3600,
            'logged_duration' => 5400,
            'calculated_start_date' => '2026-08-20',
        ]);
        $this->createMetricTask([
            'status_id' => $this->completedStatus->id,
            'estimated_duration' => 1800,
            'logged_duration' => 600,
            'calculated_start_date' => '2026-08-25',
        ]);
        $this->createMetricTask([
            'estimated_duration' => null,
            'calculated_start_date' => '2026-08-15',
        ]);
        $this->createMetricTask([
            'estimated_duration' => 9999,
            'calculated_start_date' => '2026-07-31',
        ]);
        $this->createMetricTask([
            'estimated_duration' => 5000,
            'calculated_start_date' => '2026-08-15',
            'is_deleted' => true,
        ]);

        $otherCompany = Company::factory()->create(['account_id' => $this->account->id]);
        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $otherCompany->id,
            'estimated_duration' => 8000,
            'calculated_start_date' => '2026-08-15',
            'is_deleted' => false,
        ]);

        $estimatedSum = $this->postMetric([
            'field' => 'task_estimated_duration',
            'calculation' => 'sum',
            'format' => 'time',
        ]);
        $estimatedAverage = $this->postMetric([
            'field' => 'task_estimated_duration',
            'calculation' => 'avg',
            'format' => 'time',
        ]);
        $remainingSum = $this->postMetric([
            'field' => 'task_remaining_estimated_duration',
            'calculation' => 'sum',
            'format' => 'time',
        ]);
        $remainingAverage = $this->postMetric([
            'field' => 'task_remaining_estimated_duration',
            'calculation' => 'avg',
            'format' => 'time',
        ]);

        $estimatedSum->assertOk();
        $estimatedAverage->assertOk();
        $remainingSum->assertOk();
        $remainingAverage->assertOk();

        $this->assertEquals(12600, $estimatedSum->json());
        $this->assertEquals(4200, $estimatedAverage->json());
        $this->assertEquals(3600, $remainingSum->json());
        $this->assertEquals(1800, $remainingAverage->json());
    }

    public function testTaskCountFieldsUseActiveStatusAndTheirCorrectDateColumn(): void
    {
        $this->createMetricTask([
            'estimated_duration' => null,
            'due_date' => '2026-08-20',
            'calculated_start_date' => '2026-08-05',
        ]);
        $this->createMetricTask([
            'estimated_duration' => 3600,
            'logged_duration' => 7200,
            'due_date' => '2026-08-30',
            'calculated_start_date' => '2026-08-10',
        ]);
        $this->createMetricTask([
            'estimated_duration' => 3600,
            'logged_duration' => 3600,
            'due_date' => '2026-08-31',
            'calculated_start_date' => '2026-07-01',
        ]);
        $this->createMetricTask([
            'estimated_duration' => 0,
            'due_date' => '2026-09-02',
            'calculated_start_date' => '2026-08-12',
        ]);
        $this->createMetricTask([
            'status_id' => $this->completedStatus->id,
            'estimated_duration' => null,
            'due_date' => '2026-08-30',
            'calculated_start_date' => '2026-08-12',
        ]);
        $this->createMetricTask([
            'status_id' => $this->completedStatus->id,
            'estimated_duration' => 60,
            'logged_duration' => 120,
            'due_date' => '2026-08-30',
            'calculated_start_date' => '2026-08-12',
        ]);

        $unestimated = $this->postMetric([
            'field' => 'unestimated_tasks',
            'calculation' => 'count',
        ]);
        $overEstimate = $this->postMetric([
            'field' => 'tasks_over_estimate',
            'calculation' => 'count',
        ]);
        $overdue = $this->postMetric([
            'field' => 'overdue_tasks',
            'calculation' => 'count',
        ]);
        $due = $this->postMetric([
            'field' => 'tasks_due',
            'calculation' => 'count',
            'start_date' => '2026-08-30',
            'end_date' => '2026-08-31',
        ]);

        $unestimated->assertOk();
        $overEstimate->assertOk();
        $overdue->assertOk();
        $due->assertOk();

        $this->assertSame(1, $unestimated->json());
        $this->assertSame(1, $overEstimate->json());
        $this->assertSame(2, $overdue->json());
        $this->assertSame(2, $due->json());
    }

    public function testTaskMetricFieldCombinationsAreValidated(): void
    {
        $invalidRequests = [
            [
                ['field' => 'task_estimated_duration', 'calculation' => 'count', 'format' => 'time'],
                ['calculation'],
            ],
            [
                ['field' => 'task_remaining_estimated_duration', 'calculation' => 'sum'],
                ['format'],
            ],
            [
                ['field' => 'task_estimated_duration', 'calculation' => 'avg', 'format' => 'money'],
                ['format'],
            ],
            [
                ['field' => 'unestimated_tasks', 'calculation' => 'sum'],
                ['calculation'],
            ],
            [
                ['field' => 'tasks_due', 'calculation' => 'count', 'format' => 'time'],
                ['format'],
            ],
            [
                ['field' => 'tasks_over_estimate', 'calculation' => 'avg'],
                ['calculation'],
            ],
            [
                ['field' => 'overdue_tasks', 'calculation' => 'sum'],
                ['calculation'],
            ],
            [
                ['field' => 'unestimated_tasks', 'calculation' => 'count', 'format' => 'money'],
                ['format'],
            ],
            [
                ['field' => 'not_a_metric', 'calculation' => 'count'],
                ['field'],
            ],
            [
                ['calculation' => 'count'],
                ['field'],
            ],
            [
                ['field' => 'task_estimated_duration', 'format' => 'time'],
                ['calculation'],
            ],
            [
                ['field' => 'unestimated_tasks', 'calculation' => 'count', 'period' => null],
                ['period'],
            ],
        ];

        foreach ($invalidRequests as [$payload, $invalidFields]) {
            $this->postMetric($payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($invalidFields);
        }
    }

    public function testTaskMetricsReturnZeroWhenNoTasksMatch(): void
    {
        Task::query()
            ->where('company_id', $this->company->id)
            ->update(['is_deleted' => true]);

        $metrics = [
            ['task_estimated_duration', 'sum', 'time'],
            ['task_estimated_duration', 'avg', 'time'],
            ['task_remaining_estimated_duration', 'sum', 'time'],
            ['task_remaining_estimated_duration', 'avg', 'time'],
            ['unestimated_tasks', 'count', null],
            ['tasks_over_estimate', 'count', null],
            ['overdue_tasks', 'count', null],
            ['tasks_due', 'count', null],
        ];

        foreach ($metrics as [$field, $calculation, $format]) {
            $payload = compact('field', 'calculation');

            if ($format !== null) {
                $payload['format'] = $format;
            }

            $response = $this->postMetric($payload)->assertOk();

            $this->assertEquals(0, $response->json(), "{$field}:{$calculation} did not return zero");
        }
    }

    public function testTaskMetricsDistinguishZeroEstimatesFromMissingEstimates(): void
    {
        $this->createMetricTask([
            'estimated_duration' => 0,
            'calculated_start_date' => '2026-08-10',
        ]);
        $this->createMetricTask([
            'estimated_duration' => 3600,
            'calculated_start_date' => '2026-08-11',
        ]);
        $this->createMetricTask([
            'estimated_duration' => null,
            'calculated_start_date' => '2026-08-12',
        ]);

        $estimatedAverage = $this->postMetric([
            'field' => 'task_estimated_duration',
            'calculation' => 'avg',
            'format' => 'time',
        ])->assertOk();
        $unestimated = $this->postMetric([
            'field' => 'unestimated_tasks',
            'calculation' => 'count',
        ])->assertOk();

        $this->assertEquals(1800, $estimatedAverage->json());
        $this->assertSame(1, $unestimated->json());
    }

    public function testTaskMetricsSupportPreviousAndAllTimePeriods(): void
    {
        Task::query()
            ->where('company_id', $this->company->id)
            ->update(['is_deleted' => true]);

        $this->createMetricTask([
            'estimated_duration' => 1800,
            'due_date' => '2026-07-20',
            'calculated_start_date' => '2026-07-10',
        ]);
        $this->createMetricTask([
            'estimated_duration' => 3600,
            'due_date' => '2026-08-20',
            'calculated_start_date' => '2026-08-10',
        ]);

        $previousEstimate = $this->postMetric([
            'date_range' => 'this_month',
            'period' => 'previous',
            'field' => 'task_estimated_duration',
            'calculation' => 'sum',
            'format' => 'time',
        ])->assertOk();
        $previousDue = $this->postMetric([
            'date_range' => 'this_month',
            'period' => 'previous',
            'field' => 'tasks_due',
            'calculation' => 'count',
        ])->assertOk();
        $allTimeEstimate = $this->postMetric([
            'date_range' => 'all_time',
            'period' => 'total',
            'field' => 'task_estimated_duration',
            'calculation' => 'sum',
            'format' => 'time',
        ])->assertOk();
        $allTimeDue = $this->postMetric([
            'date_range' => 'all_time',
            'period' => 'total',
            'field' => 'tasks_due',
            'calculation' => 'count',
        ])->assertOk();

        $this->assertEquals(1800, $previousEstimate->json());
        $this->assertSame(1, $previousDue->json());
        $this->assertEquals(5400, $allTimeEstimate->json());
        $this->assertSame(2, $allTimeDue->json());
    }

    public function testOverdueTasksUseTheCompanyLocalDateBoundary(): void
    {
        Task::query()
            ->where('company_id', $this->company->id)
            ->update(['is_deleted' => true]);

        $settings = $this->company->settings;
        $settings->timezone_id = '113';
        $this->company->settings = $settings;
        $this->company->save();

        Carbon::setTestNow(Carbon::parse('2026-08-31 12:30:00 UTC'));

        $this->createMetricTask([
            'due_date' => '2026-08-31',
            'calculated_start_date' => '2026-08-31',
        ]);
        $this->createMetricTask([
            'due_date' => '2026-09-01',
            'calculated_start_date' => '2026-08-31',
        ]);

        $response = $this->postMetric([
            'date_range' => 'all_time',
            'period' => 'total',
            'field' => 'overdue_tasks',
            'calculation' => 'count',
        ])->assertOk();

        $this->assertSame(1, $response->json());
    }

    public function testSoftDeletedTaskStatusesStillDetermineWhetherTasksAreActive(): void
    {
        Task::query()
            ->where('company_id', $this->company->id)
            ->update(['is_deleted' => true]);

        $activeStatus = TaskStatus::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'Archived Active',
            'status_order' => 3,
        ]);
        $completedStatus = TaskStatus::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'Archived Completed',
            'status_order' => 4,
        ]);

        $this->createMetricTask([
            'status_id' => $activeStatus->id,
            'estimated_duration' => null,
        ]);
        $this->createMetricTask([
            'status_id' => $completedStatus->id,
            'estimated_duration' => null,
        ]);

        $activeStatus->delete();
        $completedStatus->delete();

        $response = $this->postMetric([
            'field' => 'unestimated_tasks',
            'calculation' => 'count',
        ])->assertOk();

        $this->assertSame(1, $response->json());
    }

    public function testRunningTaskElapsedTimeCanExhaustItsEstimate(): void
    {
        Task::query()
            ->where('company_id', $this->company->id)
            ->update(['is_deleted' => true]);

        $startTimestamp = time() - 7200;

        Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => $this->activeStatus->id,
            'estimated_duration' => 3600,
            'calculated_start_date' => '2026-08-15',
            'time_log' => json_encode([[$startTimestamp, 0, '', true]]),
            'duration' => 0,
            'is_deleted' => false,
            'is_running' => true,
        ]);

        $remaining = $this->postMetric([
            'field' => 'task_remaining_estimated_duration',
            'calculation' => 'sum',
            'format' => 'time',
        ])->assertOk();
        $overEstimate = $this->postMetric([
            'field' => 'tasks_over_estimate',
            'calculation' => 'count',
        ])->assertOk();

        $this->assertEquals(0, $remaining->json());
        $this->assertSame(1, $overEstimate->json());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createMetricTask(array $attributes): Task
    {
        $loggedDuration = (int) ($attributes['logged_duration'] ?? 0);
        unset($attributes['logged_duration']);

        $startTimestamp = Carbon::parse('2026-08-01 09:00:00 UTC')->timestamp;
        $timeLog = $loggedDuration > 0
            ? json_encode([[$startTimestamp, $startTimestamp + $loggedDuration, '', true]])
            : json_encode([]);

        return Task::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => $this->activeStatus->id,
            'estimated_duration' => null,
            'due_date' => null,
            'calculated_start_date' => '2026-08-15',
            'time_log' => $timeLog,
            'duration' => $loggedDuration,
            'is_deleted' => false,
            'is_running' => false,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postMetric(array $payload): TestResponse
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/charts/calculated_fields', array_merge([
            'date_range' => 'custom',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'period' => 'current',
        ], $payload));
    }
}
