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

namespace Tests\Unit\EDocument\France;

use App\Services\EDocument\Standards\France\ReportingCalendar;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ReportingCalendarTest extends TestCase
{
    private function date(string $ymd): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $ymd)->startOfDay();
    }

    private function assertPeriod(
        ReportingProfile $profile,
        string $referenceDate,
        string $expectedStart,
        string $expectedEnd,
        string $expectedDueDate,
        ?string $expectedLabel = null,
    ): void {
        $period = ReportingCalendar::currentPeriod($profile, $this->date($referenceDate));

        $this->assertSame(
            $expectedStart,
            $period->start->format('Y-m-d H:i:s'),
            "Start mismatch for {$profile->value} on {$referenceDate}"
        );
        $this->assertSame(
            $expectedEnd,
            $period->end->format('Y-m-d H:i:s'),
            "End mismatch for {$profile->value} on {$referenceDate}"
        );
        $this->assertSame(
            $expectedDueDate,
            $period->dueDate->format('Y-m-d H:i:s'),
            "Due date mismatch for {$profile->value} on {$referenceDate}"
        );

        if ($expectedLabel !== null) {
            $this->assertSame($expectedLabel, $period->label);
        }
    }

    // -------- Ten-day profile: period 1 (1st-10th) --------

    public function testTenDayPeriodOneAtStartOfMonth(): void
    {
        $this->assertPeriod(
            ReportingProfile::TenDay,
            '2026-03-01',
            '2026-03-01 00:00:00',
            '2026-03-10 23:59:59',
            '2026-03-20 23:59:59',
            '2026-03-01 → 2026-03-10',
        );
    }

    public function testTenDayPeriodOneOnBoundary(): void
    {
        $this->assertPeriod(
            ReportingProfile::TenDay,
            '2026-03-10',
            '2026-03-01 00:00:00',
            '2026-03-10 23:59:59',
            '2026-03-20 23:59:59',
        );
    }

    // -------- Ten-day profile: period 2 (11th-20th) --------

    public function testTenDayPeriodTwo(): void
    {
        $this->assertPeriod(
            ReportingProfile::TenDay,
            '2026-03-15',
            '2026-03-11 00:00:00',
            '2026-03-20 23:59:59',
            '2026-03-30 23:59:59',
            '2026-03-11 → 2026-03-20',
        );
    }

    public function testTenDayPeriodTwoFebruaryFallsBackToEndOfMonth(): void
    {
        // February has no 30th — due date collapses to end of February.
        $this->assertPeriod(
            ReportingProfile::TenDay,
            '2026-02-15',
            '2026-02-11 00:00:00',
            '2026-02-20 23:59:59',
            '2026-02-28 23:59:59',
        );
    }

    public function testTenDayPeriodTwoFebruaryLeapYearFallsBackToEndOfMonth(): void
    {
        // 2028 is a leap year — due date collapses to Feb 29.
        $this->assertPeriod(
            ReportingProfile::TenDay,
            '2028-02-15',
            '2028-02-11 00:00:00',
            '2028-02-20 23:59:59',
            '2028-02-29 23:59:59',
        );
    }

    // -------- Ten-day profile: period 3 (21st-EOM) --------

    public function testTenDayPeriodThreeRegularMonth(): void
    {
        $this->assertPeriod(
            ReportingProfile::TenDay,
            '2026-03-25',
            '2026-03-21 00:00:00',
            '2026-03-31 23:59:59',
            '2026-04-10 23:59:59',
            '2026-03-21 → 2026-03-31',
        );
    }

    public function testTenDayPeriodThreeFebruary(): void
    {
        $this->assertPeriod(
            ReportingProfile::TenDay,
            '2026-02-25',
            '2026-02-21 00:00:00',
            '2026-02-28 23:59:59',
            '2026-03-10 23:59:59',
        );
    }

    public function testTenDayPeriodThreeYearRollover(): void
    {
        $this->assertPeriod(
            ReportingProfile::TenDay,
            '2026-12-28',
            '2026-12-21 00:00:00',
            '2026-12-31 23:59:59',
            '2027-01-10 23:59:59',
        );
    }

    // -------- Monthly profile --------

    public function testMonthlyMidMonth(): void
    {
        $this->assertPeriod(
            ReportingProfile::Monthly,
            '2026-03-15',
            '2026-03-01 00:00:00',
            '2026-03-31 23:59:59',
            '2026-04-10 23:59:59',
            'March 2026',
        );
    }

    public function testMonthlyFebruary(): void
    {
        $this->assertPeriod(
            ReportingProfile::Monthly,
            '2026-02-15',
            '2026-02-01 00:00:00',
            '2026-02-28 23:59:59',
            '2026-03-10 23:59:59',
            'February 2026',
        );
    }

    public function testMonthlyDecemberRollsIntoJanuary(): void
    {
        $this->assertPeriod(
            ReportingProfile::Monthly,
            '2026-12-15',
            '2026-12-01 00:00:00',
            '2026-12-31 23:59:59',
            '2027-01-10 23:59:59',
            'December 2026',
        );
    }

    // -------- Bi-monthly profile --------

    public function testBiMonthlyJanFebFromOddMonth(): void
    {
        $this->assertPeriod(
            ReportingProfile::BiMonthly,
            '2026-01-15',
            '2026-01-01 00:00:00',
            '2026-02-28 23:59:59',
            '2026-03-10 23:59:59',
            'Jan 2026 → Feb 2026',
        );
    }

    public function testBiMonthlyJanFebFromEvenMonth(): void
    {
        // Feb (even) should resolve back to the Jan-Feb pair.
        $this->assertPeriod(
            ReportingProfile::BiMonthly,
            '2026-02-15',
            '2026-01-01 00:00:00',
            '2026-02-28 23:59:59',
            '2026-03-10 23:59:59',
            'Jan 2026 → Feb 2026',
        );
    }

    public function testBiMonthlyMarApr(): void
    {
        $this->assertPeriod(
            ReportingProfile::BiMonthly,
            '2026-04-30',
            '2026-03-01 00:00:00',
            '2026-04-30 23:59:59',
            '2026-05-10 23:59:59',
            'Mar 2026 → Apr 2026',
        );
    }

    public function testBiMonthlyMayJun(): void
    {
        $this->assertPeriod(
            ReportingProfile::BiMonthly,
            '2026-05-01',
            '2026-05-01 00:00:00',
            '2026-06-30 23:59:59',
            '2026-07-10 23:59:59',
            'May 2026 → Jun 2026',
        );
    }

    public function testBiMonthlyNovDecYearRollover(): void
    {
        $this->assertPeriod(
            ReportingProfile::BiMonthly,
            '2026-12-15',
            '2026-11-01 00:00:00',
            '2026-12-31 23:59:59',
            '2027-01-10 23:59:59',
            'Nov 2026 → Dec 2026',
        );
    }

    // -------- Cross-cutting --------

    public function testDefaultsToNowWhenDateOmitted(): void
    {
        $period = ReportingCalendar::currentPeriod(ReportingProfile::Monthly);
        $now = CarbonImmutable::now();

        $this->assertSame($now->startOfMonth()->toDateString(), $period->start->toDateString());
        $this->assertSame($now->endOfMonth()->toDateString(), $period->end->toDateString());
    }
}
