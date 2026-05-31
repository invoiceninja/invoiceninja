<?php

namespace Tests\Feature;

use App\DataMapper\TaskMeta;
use App\Factory\TaskFactory;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\MockAccountData;
use Tests\TestCase;

class CalendarTaskDuplicateTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testTaskCreationNormalizesCalendarEventMetaToTheCurrentUser(): void
    {
        $eventId = 'google:' . sha1('primary') . ':event-1';

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/tasks', [
                'description' => 'Calendar task',
                'time_log' => [],
                'meta' => [
                    'provider_event_id' => $eventId,
                ],
            ]);

        $response->assertOk();

        $task = Task::query()
            ->where('user_id', $this->user->id)
            ->where('description', 'Calendar task')
            ->firstOrFail();

        $this->assertInstanceOf(TaskMeta::class, $task->meta);
        $this->assertSame($this->user->id . ':' . $eventId, $task->meta->calendar_event_id);
    }

    public function testTaskCreationRejectsDuplicateCalendarEventForTheSameUser(): void
    {
        $eventId = $this->user->id . ':google:' . sha1('primary') . ':event-1';
        $existingTask = TaskFactory::create($this->company->id, $this->user->id);
        $existingTask->description = 'Existing calendar task';
        $existingTask->meta = new TaskMeta(calendar_event_id: $eventId);
        $existingTask->saveQuietly();

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/tasks', [
                'description' => 'Duplicate calendar task',
                'time_log' => [],
                'meta' => [
                    'calendar_event_id' => $eventId,
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['meta.calendar_event_id']);
    }

    public function testTaskCreationAllowsTheSameProviderEventForADifferentUser(): void
    {
        $providerEventId = 'google:' . sha1('primary') . ':event-1';
        $otherUser = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => 'other-calendar-user@example.com',
        ]);
        $existingTask = TaskFactory::create($this->company->id, $otherUser->id);
        $existingTask->description = 'Other user calendar task';
        $existingTask->meta = new TaskMeta(calendar_event_id: $otherUser->id . ':' . $providerEventId);
        $existingTask->saveQuietly();

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/tasks', [
                'description' => 'Allowed calendar task',
                'time_log' => [],
                'meta' => [
                    'provider_event_id' => $providerEventId,
                ],
            ]);

        $response->assertOk();

        $task = Task::query()
            ->where('user_id', $this->user->id)
            ->where('description', 'Allowed calendar task')
            ->firstOrFail();

        $this->assertSame($this->user->id . ':' . $providerEventId, $task->meta->calendar_event_id);
    }

    public function testTaskCreationReturnsValidationWhenCalendarEventCreationIsAlreadyLocked(): void
    {
        $providerEventId = 'google:' . sha1('primary') . ':event-locked';
        $calendarEventId = $this->user->id . ':' . $providerEventId;
        $lock = Cache::lock('task-calendar-event:' . $this->user->id . ':' . sha1($calendarEventId), 10);

        $this->assertTrue($lock->get());

        try {
            $response = $this->withHeaders($this->apiHeaders())
                ->postJson('/api/v1/tasks', [
                    'description' => 'Locked calendar task',
                    'time_log' => [],
                    'meta' => [
                        'provider_event_id' => $providerEventId,
                    ],
                ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['meta.calendar_event_id']);

            $this->assertFalse(Task::query()
                ->where('user_id', $this->user->id)
                ->where('description', 'Locked calendar task')
                ->exists());
        } finally {
            $lock->release();
        }
    }

    public function testTaskUpdateDoesNotUseCalendarEventCreationLock(): void
    {
        $providerEventId = 'google:' . sha1('primary') . ':event-update-lock';
        $calendarEventId = $this->user->id . ':' . $providerEventId;
        $task = TaskFactory::create($this->company->id, $this->user->id);
        $task->description = 'Unlocked update calendar task';
        $task->saveQuietly();

        $lock = Cache::lock('task-calendar-event:' . $this->user->id . ':' . sha1($calendarEventId), 10);

        $this->assertTrue($lock->get());

        try {
            $response = $this->withHeaders($this->apiHeaders())
                ->putJson('/api/v1/tasks/' . $task->hashed_id, [
                    'description' => 'Updated while calendar event is locked',
                    'time_log' => [],
                    'meta' => [
                        'provider_event_id' => $providerEventId,
                    ],
                ]);

            $response->assertOk();

            $task = $task->fresh();

            $this->assertSame('Updated while calendar event is locked', $task->description);
            $this->assertSame($calendarEventId, $task->meta->calendar_event_id);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }
}
