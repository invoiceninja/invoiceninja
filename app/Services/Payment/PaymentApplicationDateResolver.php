<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://www.invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Payment;

use App\Models\Paymentable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PaymentApplicationDateResolver
{
    public function resolve(?Paymentable $paymentable, string $timezone): ?string
    {
        if (! $paymentable?->created_at) {
            return null;
        }

        return $this->resolveTimestamp($paymentable->created_at, $timezone);
    }

    public function resolveTimestamp(mixed $created_at, string $timezone): ?string
    {
        if (! $created_at) {
            return null;
        }

        $application_date = match (true) {
            is_numeric($created_at) => CarbonImmutable::createFromTimestampUTC((int) $created_at),
            $created_at instanceof CarbonInterface => CarbonImmutable::instance($created_at)->utc(),
            default => CarbonImmutable::parse($created_at, 'UTC'),
        };

        if ($application_date->format('H:i:s') === '00:00:00') {
            return $application_date->toDateString();
        }

        return $application_date->setTimezone($timezone)->toDateString();
    }

    public function encodeBusinessDate(string $date, string $timezone): int
    {
        return CarbonImmutable::parse($date, $timezone)
            ->startOfDay()
            ->utc()
            ->timestamp;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function candidateBounds(string $start_date, string $end_date, string $timezone): array
    {
        $instant_start = CarbonImmutable::parse($start_date, $timezone)->startOfDay()->utc();
        $instant_end = CarbonImmutable::parse($end_date, $timezone)->addDay()->startOfDay()->utc();
        $legacy_start = CarbonImmutable::parse($start_date, 'UTC')->startOfDay();
        $legacy_end = CarbonImmutable::parse($end_date, 'UTC')->addDay()->startOfDay();

        return [
            $instant_start->lessThan($legacy_start) ? $instant_start : $legacy_start,
            $instant_end->greaterThan($legacy_end) ? $instant_end : $legacy_end,
        ];
    }
}
