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
use App\Services\EDocument\Standards\France\ReportingPeriod;
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

    public function testEveryCalendarDayInNextTenYearsResolvesExpectedReportingPeriod(): void
    {
        $startDate = CarbonImmutable::today('Europe/Paris');
        $endDate = $startDate->addYears(10)->subDay();
        $artifactRows = $this->calendarArtifactRows($startDate, $endDate);
        $artifactPath = $this->writeCalendarArtifact($artifactRows);

        $this->assertFileExists($artifactPath);

        foreach ($artifactRows as $row) {
            $message = "{$row['profile']} on {$row['date']}";

            $this->assertSame($row['expected_start'], $row['actual_start'], "Start mismatch for {$message}");
            $this->assertSame($row['expected_end'], $row['actual_end'], "End mismatch for {$message}");
            $this->assertSame($row['expected_due'], $row['actual_due'], "Due date mismatch for {$message}");
            $this->assertSame($row['expected_label'], $row['actual_label'], "Label mismatch for {$message}");
            $this->assertSame('yes', $row['date_contained_by_actual_period'], "Date not contained by period for {$message}");
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function calendarArtifactRows(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $rows = [];

        for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
            foreach (ReportingProfile::cases() as $profile) {
                $period = ReportingCalendar::currentPeriod($profile, $date);
                $expected = $this->expectedPeriod($profile, $date);
                $actual = $this->actualPeriod($period);

                $rows[] = [
                    'date' => $date->toDateString(),
                    'profile' => $profile->value,
                    'expected_start' => $expected['start'],
                    'actual_start' => $actual['start'],
                    'expected_end' => $expected['end'],
                    'actual_end' => $actual['end'],
                    'expected_due' => $expected['due'],
                    'actual_due' => $actual['due'],
                    'expected_label' => $expected['label'],
                    'actual_label' => $actual['label'],
                    'date_contained_by_actual_period' => $this->dateIsContainedByPeriod($date, $period) ? 'yes' : 'no',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{start: string, end: string, due: string, label: string}
     */
    private function actualPeriod(ReportingPeriod $period): array
    {
        return [
            'start' => $period->start->format('Y-m-d H:i:s'),
            'end' => $period->end->format('Y-m-d H:i:s'),
            'due' => $period->dueDate->format('Y-m-d H:i:s'),
            'label' => $period->label,
        ];
    }

    private function dateIsContainedByPeriod(CarbonImmutable $date, ReportingPeriod $period): bool
    {
        return $date->toDateString() >= $period->start->toDateString()
            && $date->toDateString() <= $period->end->toDateString();
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function writeCalendarArtifact(array $rows): string
    {
        $path = base_path('tests/artifacts/fr_reporting_calendar_10_years.csv');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Unable to write France reporting calendar artifact to [{$path}].");
        }

        fputcsv($handle, [
            'date',
            'profile',
            'expected_start',
            'actual_start',
            'expected_end',
            'actual_end',
            'expected_due',
            'actual_due',
            'expected_label',
            'actual_label',
            'date_contained_by_actual_period',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    /**
     * @return array{start: string, end: string, due: string, label: string}
     */
    private function expectedPeriod(ReportingProfile $profile, CarbonImmutable $date): array
    {
        return match ($profile) {
            ReportingProfile::TenDay => $this->expectedTenDayPeriod($date),
            ReportingProfile::Monthly => $this->expectedMonthlyPeriod($date),
            ReportingProfile::BiMonthly => $this->expectedBiMonthlyPeriod($date),
        };
    }

    /**
     * @return array{start: string, end: string, due: string, label: string}
     */
    private function expectedTenDayPeriod(CarbonImmutable $date): array
    {
        $year = $date->year;
        $month = $date->month;

        if ($date->day <= 10) {
            $start = $this->calendarDate($year, $month, 1);
            $end = $this->calendarDate($year, $month, 10)->endOfDay();
            $due = $this->calendarDate($year, $month, 20)->endOfDay();
        } elseif ($date->day <= 20) {
            $start = $this->calendarDate($year, $month, 11);
            $end = $this->calendarDate($year, $month, 20)->endOfDay();
            $due = $this->calendarDate($year, $month, min(30, $this->daysInMonth($year, $month)))->endOfDay();
        } else {
            $start = $this->calendarDate($year, $month, 21);
            $end = $this->calendarDate($year, $month, $this->daysInMonth($year, $month))->endOfDay();
            [$dueYear, $dueMonth] = $this->nextMonth($year, $month);
            $due = $this->calendarDate($dueYear, $dueMonth, 10)->endOfDay();
        }

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'due' => $due->format('Y-m-d H:i:s'),
            'label' => $start->toDateString().' → '.$end->toDateString(),
        ];
    }

    /**
     * @return array{start: string, end: string, due: string, label: string}
     */
    private function expectedMonthlyPeriod(CarbonImmutable $date): array
    {
        $year = $date->year;
        $month = $date->month;
        $start = $this->calendarDate($year, $month, 1);
        $end = $this->calendarDate($year, $month, $this->daysInMonth($year, $month))->endOfDay();
        [$dueYear, $dueMonth] = $this->nextMonth($year, $month);
        $due = $this->calendarDate($dueYear, $dueMonth, 10)->endOfDay();

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'due' => $due->format('Y-m-d H:i:s'),
            'label' => $start->format('F Y'),
        ];
    }

    /**
     * @return array{start: string, end: string, due: string, label: string}
     */
    private function expectedBiMonthlyPeriod(CarbonImmutable $date): array
    {
        $year = $date->year;
        $startMonth = $date->month % 2 === 0 ? $date->month - 1 : $date->month;
        $endMonth = $startMonth + 1;
        $start = $this->calendarDate($year, $startMonth, 1);
        $end = $this->calendarDate($year, $endMonth, $this->daysInMonth($year, $endMonth))->endOfDay();
        [$dueYear, $dueMonth] = $this->nextMonth($year, $endMonth);
        $due = $this->calendarDate($dueYear, $dueMonth, 10)->endOfDay();

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'due' => $due->format('Y-m-d H:i:s'),
            'label' => $start->format('M Y').' → '.$end->format('M Y'),
        ];
    }

    private function calendarDate(int $year, int $month, int $day): CarbonImmutable
    {
        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'Europe/Paris');
    }

    private function daysInMonth(int $year, int $month): int
    {
        return $this->calendarDate($year, $month, 1)->daysInMonth;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function nextMonth(int $year, int $month): array
    {
        if ($month === 12) {
            return [$year + 1, 1];
        }

        return [$year, $month + 1];
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
