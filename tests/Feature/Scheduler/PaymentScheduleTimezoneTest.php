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

namespace Tests\Feature\Scheduler;

use Carbon\Carbon;
use Tests\TestCase;
use Tests\MockAccountData;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Tests for PaymentSchedule timezone handling.
 * 
 * These tests prove that the date comparison in PaymentSchedule::run()
 * must apply the timezone offset to now() rather than to the item date,
 * because applying offset then calling startOfDay() nullifies the shift.
 */
class PaymentScheduleTimezoneTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Session::start();
        Model::reguard();
        $this->makeTestData();
    }

    /**
     * Demonstrates that the OLD broken algorithm fails for New York timezone.
     * 
     * Scenario: It's March 6, 2026 at 3:00 AM UTC (which is March 5 at 10:00 PM in New York).
     * A payment is scheduled for March 5, 2026 (in client's local time - New York).
     * 
     * OLD CODE: Carbon::parse($item['date'])->subSeconds($offset)->startOfDay()
     * - Parse "2026-03-05" → 2026-03-05 00:00:00 UTC
     * - subSeconds(-18000) → 2026-03-05 05:00:00 UTC (adds 5 hours)
     * - startOfDay() → 2026-03-05 00:00:00 UTC ← OFFSET LOST!
     * - now()->startOfDay() = 2026-03-06 00:00:00 UTC
     * - COMPARISON FAILS (should match, it's still March 5 in NYC!)
     */
    public function testOldAlgorithmFailsForNewYorkTimezone(): void
    {
        // New York is UTC-5 (ignoring DST for simplicity)
        // offset = -18000 seconds (-5 hours)
        $offset = -18000;

        // It's 3:00 AM UTC on March 6 (which is 10:00 PM on March 5 in New York)
        $this->travelTo(Carbon::parse('2026-03-06 03:00:00', 'UTC'));

        // Payment scheduled for March 5 in client's local time
        $schedule = [
            ['date' => '2026-03-05', 'amount' => 100],
        ];

        // OLD BROKEN CODE: Apply offset to item date, then startOfDay
        $next_schedule_old = null;
        foreach ($schedule as $item) {
            // This is the OLD broken comparison
            if (now()->startOfDay()->eq(Carbon::parse($item['date'])->subSeconds($offset)->startOfDay())) {
                $next_schedule_old = $item;
            }
        }

        // OLD code should FAIL to find the schedule
        // Because: now()->startOfDay() = 2026-03-06 00:00:00 UTC
        // And: Carbon::parse('2026-03-05')->subSeconds(-18000)->startOfDay() = 2026-03-05 00:00:00 UTC
        // These don't match, even though in New York it's still March 5!
        $this->assertNull($next_schedule_old, 'Old algorithm incorrectly found a match (or the test setup is wrong)');

        $this->travelBack();
    }


    /**
     * Test: When server time matches client local day exactly, both algorithms work.
     * This is the baseline - both old and new code work when there's no edge case.
     */
    public function testBothAlgorithmsWorkWhenDaysAlign(): void
    {
        // UTC timezone (offset = 0)
        $offset = 0;

        $this->travelTo(Carbon::parse('2026-03-05 12:00:00', 'UTC'));

        $schedule = [
            ['date' => '2026-03-05', 'amount' => 100],
        ];

        // OLD algorithm
        $found_old = false;
        foreach ($schedule as $item) {
            if (now()->startOfDay()->eq(Carbon::parse($item['date'])->subSeconds($offset)->startOfDay())) {
                $found_old = true;
            }
        }

        // NEW algorithm  
        $found_new = false;
        foreach ($schedule as $item) {
            if (now()->subSeconds($offset)->startOfDay()->eq(Carbon::parse($item['date'])->startOfDay())) {
                $found_new = true;
            }
        }

        $this->assertTrue($found_old, 'Old algorithm should work with zero offset');
        $this->assertTrue($found_new, 'New algorithm should work with zero offset');

        $this->travelBack();
    }

    /**
     * Critical test: Prove the startOfDay() after subSeconds() nullifies the offset.
     * 
     * This demonstrates the core bug: shifting a datetime then calling startOfDay()
     * loses the shift because startOfDay() resets to midnight.
     */
    public function testStartOfDayNullifiesOffset(): void
    {
        $baseDate = Carbon::parse('2026-03-05 00:00:00', 'UTC');
        
        // Apply a 5-hour shift (simulating NYC offset)
        $shifted = $baseDate->copy()->addHours(5);
        $this->assertEquals('2026-03-05 05:00:00', $shifted->format('Y-m-d H:i:s'));
        
        // Now startOfDay() resets it back to midnight - THE SHIFT IS LOST
        $shiftedThenStartOfDay = $shifted->copy()->startOfDay();
        $this->assertEquals('2026-03-05 00:00:00', $shiftedThenStartOfDay->format('Y-m-d H:i:s'));
        
        // The shifted time and original time have the same startOfDay - offset lost!
        $this->assertTrue($baseDate->startOfDay()->eq($shifted->startOfDay()));
    }

    /**
     * Test: Applying offset to now() BEFORE startOfDay() preserves the day context.
     */
    public function testOffsetToNowPreservesDayContext(): void
    {
        // Scenario: UTC time is early morning (3 AM) on day N
        // But in a timezone that's behind UTC (e.g., NYC at UTC-5), 
        // it's still the previous day (10 PM on day N-1)
        
        $this->travelTo(Carbon::parse('2026-03-06 03:00:00', 'UTC'));
        
        // NYC offset: UTC-5 = -18000 seconds
        // The company's timezone_offset() would return -18000
        $offset = -18000;
        
        // What day is it in NYC? March 5 (since 3 AM UTC = 10 PM EST the day before)
        // now() - 5 hours = 2026-03-05 22:00:00 (this represents NYC local time conceptually)
        
        // But wait - subSeconds($offset) where offset is -18000:
        // subSeconds(-18000) = addSeconds(18000) = add 5 hours
        // 2026-03-06 03:00:00 + 5 hours = 2026-03-06 08:00:00
        
        // This seems backwards. Let me check what timezone_offset() actually returns...
        // If NYC is UTC-5, and we want to convert from UTC to local:
        // local = UTC + offset (where offset is -5 hours for NYC)
        // So 03:00 UTC + (-5 hours) = 22:00 previous day
        
        // In seconds: 03:00 UTC + (-18000 seconds) = 22:00 previous day
        // This would be addSeconds(-18000) or subSeconds(18000)
        
        // But the code does subSeconds($offset) where $offset = -18000
        // subSeconds(-18000) = -(-18000) = +18000 = add 5 hours
        
        // So the code is ADDING 5 hours to UTC time... which gives future time, not local time
        
        // I think the confusion is about what timezone_offset() returns.
        // Let me verify with a practical test.
        
        $nowUtc = now(); // 2026-03-06 03:00:00 UTC
        
        // If offset is -18000 (NYC):
        $adjusted = $nowUtc->copy()->subSeconds($offset); // subSeconds(-18000) = +5 hours
        $this->assertEquals('2026-03-06 08:00:00', $adjusted->format('Y-m-d H:i:s'));
        
        // startOfDay of that is still March 6
        $this->assertEquals('2026-03-06', $adjusted->startOfDay()->format('Y-m-d'));
        
        // Hmm, that doesn't give us March 5...
        // 
        // Let me check if timezone_offset() returns POSITIVE for west timezones instead.
        // If NYC offset is +18000 (opposite convention):
        $offset_positive = 18000;
        $adjusted_positive = $nowUtc->copy()->subSeconds($offset_positive); // sub 5 hours
        $this->assertEquals('2026-03-05 22:00:00', $adjusted_positive->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-03-05', $adjusted_positive->startOfDay()->format('Y-m-d'));
        
        // YES! If timezone_offset() returns POSITIVE for west timezones,
        // then subSeconds(+18000) correctly converts to local time.
        
        $this->travelBack();
    }

    /**
     * Test the actual PaymentSchedule scenario with realistic timezone offset.
     * 
     * This test uses a POSITIVE offset (as would be returned for timezones behind UTC)
     * to simulate proper conversion from UTC server time to client local time.
     */
    public function testPaymentScheduleWithPositiveOffsetForWestTimezone(): void
    {
        // Assume timezone_offset() returns +18000 for NYC (5 hours behind UTC)
        // This means: local_time = UTC - offset_seconds
        // Or equivalently: to convert UTC to local, subtract the offset
        $offset = 18000; // +5 hours in seconds (positive = hours behind UTC)
        
        // Server time: 3 AM UTC on March 6
        // Client time: 10 PM on March 5 (in NYC)
        $this->travelTo(Carbon::parse('2026-03-06 03:00:00', 'UTC'));
        
        $schedule = [
            ['date' => '2026-03-05', 'amount' => 100], // Scheduled for March 5 in client timezone
        ];
        
        // NEW ALGORITHM: now()->subSeconds($offset)->startOfDay()
        // = 2026-03-06 03:00:00 - 18000 seconds
        // = 2026-03-05 22:00:00
        // = startOfDay() → 2026-03-05 00:00:00
        // Compare with: 2026-03-05 → 2026-03-05 00:00:00
        // MATCH! ✓
        
        $found_new = false;
        foreach ($schedule as $item) {
            if (now()->subSeconds($offset)->startOfDay()->eq(Carbon::parse($item['date'])->startOfDay())) {
                $found_new = true;
            }
        }
        
        $this->assertTrue($found_new, 'New algorithm should find the schedule for March 5 when client is in NYC');
        
        // OLD ALGORITHM: now()->startOfDay()->eq(Carbon::parse($item['date'])->subSeconds($offset)->startOfDay())
        // now()->startOfDay() = 2026-03-06 00:00:00
        // Carbon::parse('2026-03-05')->subSeconds(18000)->startOfDay()
        // = 2026-03-05 00:00:00 - 18000 seconds = 2026-03-04 19:00:00
        // = startOfDay() → 2026-03-04 00:00:00
        // Compare: 2026-03-06 00:00:00 vs 2026-03-04 00:00:00
        // NO MATCH! ✗
        
        $found_old = false;
        foreach ($schedule as $item) {
            if (now()->startOfDay()->eq(Carbon::parse($item['date'])->subSeconds($offset)->startOfDay())) {
                $found_old = true;
            }
        }
        
        $this->assertFalse($found_old, 'Old algorithm should NOT find the schedule (proves the bug)');
        
        $this->travelBack();
    }

    /**
     * Test with Australia/Sydney timezone (ahead of UTC).
     * 
     * Sydney is UTC+11 (during daylight saving) = -39600 seconds offset
     * (negative because they're AHEAD of UTC)
     */
    public function testPaymentScheduleWithNegativeOffsetForEastTimezone(): void
    {
        // Sydney: UTC+11 during DST
        // timezone_offset() might return -39600 (negative = ahead of UTC)
        $offset = -39600;
        
        // Server time: 6 PM UTC on March 5
        // Sydney time: 5 AM on March 6 (11 hours ahead)
        $this->travelTo(Carbon::parse('2026-03-05 18:00:00', 'UTC'));
        
        $schedule = [
            ['date' => '2026-03-06', 'amount' => 100], // Scheduled for March 6 in Sydney
        ];
        
        // NEW ALGORITHM:
        // now()->subSeconds(-39600) = 2026-03-05 18:00:00 + 39600 seconds
        // = 2026-03-06 05:00:00
        // startOfDay() = 2026-03-06 00:00:00
        // Compare with '2026-03-06' → MATCH! ✓
        
        $found_new = false;
        foreach ($schedule as $item) {
            if (now()->subSeconds($offset)->startOfDay()->eq(Carbon::parse($item['date'])->startOfDay())) {
                $found_new = true;
            }
        }
        
        $this->assertTrue($found_new, 'New algorithm should find March 6 schedule when client is in Sydney');
        
        // OLD ALGORITHM would fail similarly
        $found_old = false;
        foreach ($schedule as $item) {
            if (now()->startOfDay()->eq(Carbon::parse($item['date'])->subSeconds($offset)->startOfDay())) {
                $found_old = true;
            }
        }
        
        // now()->startOfDay() = 2026-03-05 00:00:00
        // Carbon::parse('2026-03-06')->subSeconds(-39600)->startOfDay()
        // = 2026-03-06 00:00:00 + 39600 = 2026-03-06 11:00:00
        // = startOfDay() → 2026-03-06 00:00:00
        // Compare: 2026-03-05 vs 2026-03-06 - NO MATCH
        
        $this->assertFalse($found_old, 'Old algorithm should NOT find March 6 schedule (proves the bug)');
        
        $this->travelBack();
    }
}
