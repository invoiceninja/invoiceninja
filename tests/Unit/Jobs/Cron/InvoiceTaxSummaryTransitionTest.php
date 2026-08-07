<?php

namespace Tests\Unit\Jobs\Cron;

use App\Jobs\Cron\InvoiceTaxSummary;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class InvoiceTaxSummaryTransitionTest extends TestCase
{
    public function testFractionalOffsetTimezonesAreNotSelectedBeforeLocalMonthBoundary(): void
    {
        $this->setTimezones([
            ['id' => 1, 'name' => 'Australia/Adelaide'],
            ['id' => 2, 'name' => 'Asia/Kolkata'],
        ]);

        $this->assertSame(
            [],
            $this->transitioningTimezoneIds('2026-04-30 14:00:00')
        );

        $this->assertSame(
            [],
            $this->transitioningTimezoneIds('2026-04-30 18:00:00')
        );
    }

    public function testFractionalOffsetTimezonesAreSelectedOnFirstHourlyRunAfterLocalMonthBoundary(): void
    {
        $this->setTimezones([
            ['id' => 1, 'name' => 'Australia/Adelaide'],
            ['id' => 2, 'name' => 'Asia/Kolkata'],
        ]);

        $this->assertSame(
            [1],
            $this->transitioningTimezoneIds('2026-04-30 15:00:00')
        );

        $this->assertSame(
            [2],
            $this->transitioningTimezoneIds('2026-04-30 19:00:00')
        );
    }

    public function testWholeHourTimezoneIsSelectedAtBoundaryAndInvalidTimezoneIsIgnored(): void
    {
        $this->setTimezones([
            ['id' => 1, 'name' => 'UTC'],
            ['id' => 2, 'name' => 'Invalid/Timezone'],
        ]);

        $this->assertSame(
            [1],
            $this->transitioningTimezoneIds('2026-05-01 00:00:00')
        );
    }

    private function setTimezones(array $timezones): void
    {
        app()->instance('timezones', collect($timezones)->map(fn ($timezone) => (object) $timezone));
    }

    private function transitioningTimezoneIds(string $utcNow): array
    {
        $method = new ReflectionMethod(InvoiceTaxSummary::class, 'getTransitioningTimezones');
        $method->setAccessible(true);

        return $method->invoke(
            new InvoiceTaxSummary(),
            Carbon::parse($utcNow, 'UTC')
        );
    }
}
