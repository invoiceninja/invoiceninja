<?php

namespace Tests\Unit;

use App\DataMapper\TaskMeta;
use App\Models\Task;
use Tests\TestCase;

class TaskMetaCastTest extends TestCase
{
    public function testItCastsJsonIntoTaskMeta(): void
    {
        $task = new Task();
        $task->setRawAttributes([
            'meta' => json_encode([
                'calendar_event_id' => 'cal_123',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertInstanceOf(TaskMeta::class, $task->meta);
        $this->assertSame('cal_123', $task->meta->calendar_event_id);
    }

    public function testItSerializesTaskMetaForStorage(): void
    {
        $task = new Task();
        $task->meta = new TaskMeta(
            calendar_event_id: 'cal_456',
        );

        $stored = json_decode($task->getAttributes()['meta'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('cal_456', $stored['calendar_event_id']);
    }

    public function testItAcceptsArrayInputAndProviderEventIdAlias(): void
    {
        $task = new Task();
        $task->meta = [
            'provider_event_id' => 'provider_event_789',
        ];

        $this->assertSame('provider_event_789', $task->meta->calendar_event_id);

        $stored = json_decode($task->getAttributes()['meta'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('provider_event_789', $stored['calendar_event_id']);
        $this->assertArrayNotHasKey('provider_event_id', $stored);
    }

    public function testItAcceptsLegacyEventIdAlias(): void
    {
        $task = new Task();
        $task->meta = [
            'event_id' => 'legacy_event_789',
        ];

        $this->assertSame('legacy_event_789', $task->meta->calendar_event_id);

        $stored = json_decode($task->getAttributes()['meta'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('legacy_event_789', $stored['calendar_event_id']);
        $this->assertArrayNotHasKey('event_id', $stored);
    }

    public function testItReturnsNullForEmptyMeta(): void
    {
        $task = new Task();
        $task->setRawAttributes(['meta' => null]);

        $this->assertNull($task->meta);
    }

    public function testItReturnsNullWhenMetaHasNoData(): void
    {
        $task = new Task();
        $task->setRawAttributes([
            'meta' => json_encode([
                'calendar_event_id' => '',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertNull($task->meta);
    }

    public function testItStoresNullWhenAssignedEmptyTaskMeta(): void
    {
        $task = new Task();
        $task->meta = new TaskMeta();

        $this->assertNull($task->getAttributes()['meta']);
    }
}
