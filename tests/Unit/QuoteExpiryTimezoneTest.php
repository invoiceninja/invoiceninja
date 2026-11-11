<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Quote;
use App\Models\Timezone;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuoteExpiryTimezoneTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        date_default_timezone_set($this->originalTimezone);

        parent::tearDown();
    }

    #[DataProvider('expiryCases')]
    public function testExpiryDependsOnClientCalendarDate(
        string $serverTimezone,
        string $clientTimezone,
        string $dueDate,
        string $clientNow,
        bool $expectedExpired,
    ): void {
        config(['app.timezone' => $serverTimezone]);
        date_default_timezone_set($serverTimezone);
        $this->travelTo(Carbon::parse($clientNow, $clientTimezone));

        $quote = $this->quoteForTimezone($clientTimezone, $dueDate);

        // due_date is a calendar date, never an instant to convert between timezones.
        $this->assertSame($dueDate, $quote->due_date->toDateString());
        $this->assertSame($expectedExpired, $quote->hasLapsedValidUntil());
        $this->assertSame($expectedExpired, $quote->hasLapsedValidUntil($dueDate));
        $this->assertSame(
            $expectedExpired ? Quote::STATUS_EXPIRED : Quote::STATUS_SENT,
            $quote->status_id,
        );
    }

    public static function expiryCases(): iterable
    {
        $serverTimezones = ['UTC', 'Australia/Sydney', 'America/Los_Angeles'];
        $boundaries = [
            'day before due date' => ['2026-09-08 12:00:00', false],
            'start of due date' => ['2026-09-09 00:00:00', false],
            'noon on due date' => ['2026-09-09 12:00:00', false],
            'last second of due date' => ['2026-09-09 23:59:59', false],
            'last microsecond of due date' => ['2026-09-09 23:59:59.999999', false],
            'midnight after due date' => ['2026-09-10 00:00:00', true],
        ];

        foreach ($serverTimezones as $serverTimezone) {
            foreach (['UTC', 'Australia/Sydney', 'America/Los_Angeles'] as $clientTimezone) {
                foreach ($boundaries as $boundary => [$clientNow, $expectedExpired]) {
                    yield "$clientTimezone client, $serverTimezone server, $boundary" => [
                        $serverTimezone, $clientTimezone, '2026-09-09', $clientNow, $expectedExpired,
                    ];
                }
            }

            foreach ([
                ['Australia/Sydney', '2026-10-04', '2026-10-05'],
                ['Australia/Sydney', '2026-04-05', '2026-04-06'],
                ['America/Los_Angeles', '2026-03-08', '2026-03-09'],
                ['America/Los_Angeles', '2026-11-01', '2026-11-02'],
            ] as [$clientTimezone, $dueDate, $nextDate]) {
                yield "$clientTimezone client, $serverTimezone server, end of DST transition day $dueDate" => [
                    $serverTimezone, $clientTimezone, $dueDate, "$dueDate 23:59:59.999999", false,
                ];
                yield "$clientTimezone client, $serverTimezone server, midnight after DST transition day $dueDate" => [
                    $serverTimezone, $clientTimezone, $dueDate, "$nextDate 00:00:00", true,
                ];
            }
        }
    }

    public function testQuoteWithoutDueDateDoesNotExpire(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 00:00:00', 'Australia/Sydney'));
        $quote = $this->quoteForTimezone('Australia/Sydney', null);

        $this->assertFalse($quote->hasLapsedValidUntil());
        $this->assertSame(Quote::STATUS_SENT, $quote->status_id);
    }

    private function quoteForTimezone(string $clientTimezone, ?string $dueDate): Quote
    {
        $timezone = (new Timezone())->forceFill(['name' => $clientTimezone]);
        $client = Mockery::mock(Client::class)->makePartial();
        $client->shouldReceive('timezone')->andReturn($timezone);

        return (new Quote())->forceFill([
            'due_date' => $dueDate,
            'status_id' => Quote::STATUS_SENT,
            'is_deleted' => false,
        ])->setRelation('client', $client);
    }
}
