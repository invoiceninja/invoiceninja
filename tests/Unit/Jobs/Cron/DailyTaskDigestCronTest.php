<?php

namespace Tests\Unit\Jobs\Cron;

use App\Jobs\Cron\DailyTaskDigestCron;
use App\Models\CompanyUser;
use App\Models\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

class DailyTaskDigestCronTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    public function testItSelectsFractionalOffsetTimezonesWithinTheFourHourWindow(): void
    {
        $this->setTimezones([
            ['id' => 1, 'name' => 'Australia/Adelaide'],
            ['id' => 2, 'name' => 'Asia/Kolkata'],
            ['id' => 3, 'name' => 'UTC'],
        ]);

        $this->assertSame(
            ['1'],
            $this->dueTimezoneIds('2026-01-01 00:00:00')
        );

        $this->assertSame(
            ['2'],
            $this->dueTimezoneIds('2026-01-01 04:00:00')
        );
    }

    public function testItIncludesTheEndBoundaryAndExcludesTheStartBoundary(): void
    {
        $this->setTimezones([
            ['id' => 1, 'name' => 'UTC'],
            ['id' => 2, 'name' => 'Invalid/Timezone'],
        ]);

        $this->assertSame(
            ['1'],
            $this->dueTimezoneIds('2026-01-01 08:00:00')
        );

        $this->assertSame(
            [],
            $this->dueTimezoneIds('2026-01-01 12:00:00')
        );
    }

    public function testRecipientQueryRequiresTheDedicatedNotification(): void
    {
        $this->makeTestData();

        CompanyUser::query()
            ->whereKey($this->cu->id)
            ->update([
                'notifications' => json_encode(
                    ['email' => ['all_notifications']],
                    JSON_THROW_ON_ERROR
                ),
            ]);

        $this->assertFalse(
            $this->recipientQuery(['1'])->whereKey($this->cu->id)->exists()
        );

        CompanyUser::query()
            ->whereKey($this->cu->id)
            ->update([
                'notifications' => json_encode(
                    ['email' => ['task_daily_digest']],
                    JSON_THROW_ON_ERROR
                ),
            ]);

        $this->assertTrue(
            $this->recipientQuery(['1'])->whereKey($this->cu->id)->exists()
        );
    }

    public function testItIsScheduledEveryFourHours(): void
    {
        $schedule = app(ConsoleKernel::class)->resolveConsoleSchedule();
        $event = collect($schedule->events())
            ->first(fn ($event): bool => $event->description === 'daily-task-digest-job');

        $this->assertNotNull($event);
        $this->assertSame('0 */4 * * *', $event->expression);
        $this->assertSame('UTC', $event->timezone);
    }

    /**
     * @param array<int, array{id: int, name: string}> $timezones
     */
    private function setTimezones(array $timezones): void
    {
        app()->instance(
            'timezones',
            collect($timezones)->map(
                static fn (array $timezone): Timezone => (new Timezone())->forceFill($timezone)
            )
        );
    }

    /**
     * @return array<int, string>
     */
    private function dueTimezoneIds(string $utcNow): array
    {
        $method = new ReflectionMethod(DailyTaskDigestCron::class, 'getDueTimezoneIds');
        $method->setAccessible(true);

        return $method->invoke(
            new DailyTaskDigestCron(),
            CarbonImmutable::parse($utcNow, 'UTC')
        );
    }

    /**
     * @param array<int, string> $timezoneIds
     * @return Builder<CompanyUser>
     */
    private function recipientQuery(array $timezoneIds): Builder
    {
        $method = new ReflectionMethod(DailyTaskDigestCron::class, 'recipientQuery');
        $method->setAccessible(true);

        return $method->invoke(new DailyTaskDigestCron(), $timezoneIds);
    }
}
