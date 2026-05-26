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

namespace App\Services\EDocument\Standards\France;

use Carbon\CarbonImmutable;

final class ReportingCalendar
{
    public static function currentPeriod(
        ReportingProfile $profile,
        ?CarbonImmutable $date = null,
    ): ReportingPeriod {
        $date ??= CarbonImmutable::now();

        return match ($profile) {
            ReportingProfile::TenDay => self::tenDayPeriod($date),
            ReportingProfile::Monthly => self::monthlyPeriod($date),
            ReportingProfile::BiMonthly => self::biMonthlyPeriod($date),
        };
    }

    private static function tenDayPeriod(CarbonImmutable $date): ReportingPeriod
    {
        $year = $date->year;
        $month = $date->month;
        $day = $date->day;

        if ($day <= 10) {
            $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0);
            $end = CarbonImmutable::create($year, $month, 10, 23, 59, 59);
        } elseif ($day <= 20) {
            $start = CarbonImmutable::create($year, $month, 11, 0, 0, 0);
            $end = CarbonImmutable::create($year, $month, 20, 23, 59, 59);
        } else {
            $start = CarbonImmutable::create($year, $month, 21, 0, 0, 0);
            $end = $date->endOfMonth();
        }

        return new ReportingPeriod(
            start: $start,
            end: $end,
            dueDate: self::tenDayDueDate($start, $end),
            label: $start->toDateString().' → '.$end->toDateString(),
        );
    }

    private static function tenDayDueDate(
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): CarbonImmutable {
        // Period 1: 1–10 => due 20th same month
        if ($start->day === 1 && $end->day === 10) {
            return CarbonImmutable::create($start->year, $start->month, 20, 23, 59, 59);
        }

        // Period 2: 11–20 => due 30th same month,
        // except February, where there is no 30th.
        if ($start->day === 11 && $end->day === 20) {
            if ($start->month === 2) {
                return $start->endOfMonth();
            }

            return CarbonImmutable::create($start->year, $start->month, 30, 23, 59, 59);
        }

        // Period 3: 21–EOM => due 10th next month
        return $end
            ->addMonthNoOverflow()
            ->startOfMonth()
            ->setDay(10)
            ->endOfDay();
    }

    private static function monthlyPeriod(CarbonImmutable $date): ReportingPeriod
    {
        $start = $date->startOfMonth();
        $end = $date->endOfMonth();

        return new ReportingPeriod(
            start: $start,
            end: $end,
            dueDate: $end->addMonthNoOverflow()->startOfMonth()->setDay(10)->endOfDay(),
            label: $start->format('F Y'),
        );
    }

    private static function biMonthlyPeriod(CarbonImmutable $date): ReportingPeriod
    {
        $startMonth = $date->month % 2 === 0
            ? $date->month - 1
            : $date->month;

        $start = CarbonImmutable::create($date->year, $startMonth, 1, 0, 0, 0);
        $end = $start->addMonthNoOverflow()->endOfMonth();

        return new ReportingPeriod(
            start: $start,
            end: $end,
            dueDate: $end->addMonthNoOverflow()->startOfMonth()->setDay(10)->endOfDay(),
            label: $start->format('M Y').' → '.$end->format('M Y'),
        );
    }
}
