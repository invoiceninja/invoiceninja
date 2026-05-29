<?php

namespace Tests\Unit;

use App\DataMapper\TaskMeta;
use App\DataMapper\TaskNotification;
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
                'notification' => [
                    'enabled' => true,
                    'notify_at' => 1811635200,
                    'triggered_by_user_id' => 42,
                    'triggered_at' => 1811548800,
                    'sent_at' => 1811635300,
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->assertInstanceOf(TaskMeta::class, $task->meta);
        $this->assertSame('cal_123', $task->meta->calendar_event_id);
        $this->assertInstanceOf(TaskNotification::class, $task->meta->notification);
        $this->assertTrue($task->meta->notification->enabled);
        $this->assertSame(1811635200, $task->meta->notification->notify_at);
        $this->assertSame(42, $task->meta->notification->triggered_by_user_id);
        $this->assertSame(1811548800, $task->meta->notification->triggered_at);
        $this->assertSame(1811635300, $task->meta->notification->sent_at);
    }

    public function testItSerializesTaskMetaForStorage(): void
    {
        $task = new Task();
        $task->meta = new TaskMeta(
            calendar_event_id: 'cal_456',
            notification: new TaskNotification(
                enabled: true,
                notify_at: 1811635200,
                triggered_by_user_id: 42,
            ),
        );

        $stored = json_decode($task->getAttributes()['meta'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('cal_456', $stored['calendar_event_id']);
        $this->assertTrue($stored['notification']['enabled']);
        $this->assertSame(1811635200, $stored['notification']['notify_at']);
        $this->assertSame(42, $stored['notification']['triggered_by_user_id']);
    }

    public function testItAcceptsArrayInputAndLegacyEventIdAlias(): void
    {
        $task = new Task();
        $task->meta = [
            'event_id' => 'legacy_event_789',
            'notification' => [
                'enabled' => '1',
                'notify_at' => '1811635200',
            ],
        ];

        $this->assertSame('legacy_event_789', $task->meta->calendar_event_id);
        $this->assertTrue($task->meta->notification->enabled);
        $this->assertSame(1811635200, $task->meta->notification->notify_at);

        $stored = json_decode($task->getAttributes()['meta'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('legacy_event_789', $stored['calendar_event_id']);
        $this->assertArrayNotHasKey('event_id', $stored);
    }

    public function testItNormalizesStringBooleanNotificationFlags(): void
    {
        $task = new Task();
        $task->meta = [
            'notification' => [
                'enabled' => 'false',
            ],
        ];

        $this->assertFalse($task->meta->notification->enabled);
    }

    public function testItReturnsNullForEmptyMeta(): void
    {
        $task = new Task();
        $task->setRawAttributes(['meta' => null]);

        $this->assertNull($task->meta);
    }
}