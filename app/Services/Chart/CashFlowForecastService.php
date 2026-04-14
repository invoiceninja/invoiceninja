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

namespace App\Services\Chart;

use App\Models\Company;
use App\Models\RecurringInvoice;
use Carbon\Carbon;

class CashFlowForecastService
{
    private const MAX_DAILY_ITERATIONS = 365;

    private const FREQUENCY_INTERVALS = [
        RecurringInvoice::FREQUENCY_DAILY => ['addDay', 1],
        RecurringInvoice::FREQUENCY_WEEKLY => ['addWeek', 1],
        RecurringInvoice::FREQUENCY_TWO_WEEKS => ['addWeeks', 2],
        RecurringInvoice::FREQUENCY_FOUR_WEEKS => ['addWeeks', 4],
        RecurringInvoice::FREQUENCY_MONTHLY => ['addMonthNoOverflow', 1],
        RecurringInvoice::FREQUENCY_TWO_MONTHS => ['addMonthsNoOverflow', 2],
        RecurringInvoice::FREQUENCY_THREE_MONTHS => ['addMonthsNoOverflow', 3],
        RecurringInvoice::FREQUENCY_FOUR_MONTHS => ['addMonthsNoOverflow', 4],
        RecurringInvoice::FREQUENCY_SIX_MONTHS => ['addMonthsNoOverflow', 6],
        RecurringInvoice::FREQUENCY_ANNUALLY => ['addYear', 1],
        RecurringInvoice::FREQUENCY_TWO_YEARS => ['addYears', 2],
        RecurringInvoice::FREQUENCY_THREE_YEARS => ['addYears', 3],
    ];

    private const WEIGHT_AUTO_BILL = 0.95;
    private const WEIGHT_NO_AUTO_BILL = 0.75;
    private const DEFAULT_PAYMENT_DAYS = 14;
    private const FALLBACK_CONFIDENCE_MULTIPLIER = 0.5;
    private const DEFAULT_CONVERSION_RATE = 0.5;
    private const DEFAULT_CONVERSION_DAYS = 30;

    /** @var array<string, array<string, mixed>> */
    private array $buckets = [];

    public function __construct(
        private Company $company,
        private string $start_date,
        private string $end_date,
        private string $bucket_type = 'monthly',
    ) {}

    /**
     * @param array<int, \stdClass> $outstandingInvoices
     * @param array<int, \stdClass> $recurringInvoices
     * @param array<int, \stdClass> $recurringExpenses
     * @param array<int, \stdClass> $upcomingExpenses
     * @param array<int, \stdClass> $openQuotes
     * @param array<int, \stdClass> $companyPaymentSummary
     * @return array<string, mixed>
     */
    public function generate(
        array $outstandingInvoices,
        array $recurringInvoices,
        array $recurringExpenses,
        array $upcomingExpenses,
        array $openQuotes,
        array $companyPaymentSummary,
    ): array {
        $this->buildBucketGrid();

        $companyFallback = ! empty($companyPaymentSummary) ? $companyPaymentSummary[0] : null;

        $this->projectOutstandingInvoices($outstandingInvoices, $companyFallback);
        $this->projectRecurringInvoices($recurringInvoices);
        $this->projectRecurringExpenses($recurringExpenses);
        $this->projectUpcomingExpenses($upcomingExpenses);
        $this->projectOpenQuotes($openQuotes);

        return $this->buildResponse();
    }

    private function buildBucketGrid(): void
    {
        $current = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        while ($current->lte($end)) {
            if ($this->bucket_type === 'daily') {
                $key = $current->format('Y-m-d');
                $periodStart = $key;
                $periodEnd = $key;
                $current->addDay();
            } elseif ($this->bucket_type === 'weekly') {
                $key = $current->format('o-\\WW');
                $periodStart = $current->copy()->startOfWeek()->format('Y-m-d');
                $periodEnd = $current->copy()->endOfWeek()->format('Y-m-d');
                $current->addWeek();
            } else {
                $key = $current->format('Y-m');
                $periodStart = $current->copy()->startOfMonth()->format('Y-m-d');
                $periodEnd = $current->copy()->endOfMonth()->format('Y-m-d');
                $current->addMonthNoOverflow();
            }

            $this->buckets[$key] = [
                'period' => $key,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'inflows' => [
                    'outstanding_invoices' => ['amount' => 0.0, 'count' => 0, 'weighted_amount' => 0.0],
                    'recurring_invoices' => ['amount' => 0.0, 'count' => 0, 'weighted_amount' => 0.0],
                    'quote_pipeline' => ['amount' => 0.0, 'count' => 0, 'weighted_amount' => 0.0],
                    'total' => 0.0,
                    'weighted_total' => 0.0,
                ],
                'outflows' => [
                    'recurring_expenses' => ['amount' => 0.0, 'count' => 0],
                    'one_off_expenses' => ['amount' => 0.0, 'count' => 0],
                    'total' => 0.0,
                ],
                'net' => 0.0,
                'weighted_net' => 0.0,
                'confidence' => 0.0,
                '_weights' => [],
            ];
        }
    }

    private function dateToBucketKey(string $date): ?string
    {
        $d = Carbon::parse($date);
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        if ($d->lt($start) || $d->gt($end)) {
            return null;
        }

        if ($this->bucket_type === 'daily') {
            return $d->format('Y-m-d');
        }

        if ($this->bucket_type === 'weekly') {
            return $d->format('o-\\WW');
        }

        return $d->format('Y-m');
    }

    private function normalizeToCompanyCurrency(float $amount, float $exchangeRate): float
    {
        $rate = ($exchangeRate == 0) ? 1.0 : $exchangeRate;

        return $amount / $rate;
    }

    /**
     * @param array<int, \stdClass> $invoices
     */
    private function projectOutstandingInvoices(array $invoices, ?\stdClass $companyFallback): void
    {
        $companyAvgDays = $companyFallback->avg_payment_days ?? self::DEFAULT_PAYMENT_DAYS;

        foreach ($invoices as $invoice) {
            $avgDays = $invoice->client_avg_payment_days;
            $lateRatio = $invoice->client_late_ratio ?? 0;
            $dataPoints = $invoice->client_data_points ?? 0;

            if ($avgDays === null) {
                $avgDays = $companyAvgDays;
                $weight = self::FALLBACK_CONFIDENCE_MULTIPLIER;
            } else {
                $weight = (1 - $lateRatio) * min($dataPoints / 30, 1.0);
            }

            // Expected payment date based on historical avg, but never earlier than today
            $expectedDate = Carbon::parse($invoice->invoice_date)->addDays((int) round($avgDays));

            if ($expectedDate->lt(Carbon::now())) {
                $expectedDate = Carbon::now();
            }

            $bucketKey = $this->dateToBucketKey($expectedDate->format('Y-m-d'));

            if ($bucketKey === null || ! isset($this->buckets[$bucketKey])) {
                continue;
            }

            $amount = $this->normalizeToCompanyCurrency((float) $invoice->balance, (float) ($invoice->exchange_rate ?? 1));

            $this->buckets[$bucketKey]['inflows']['outstanding_invoices']['amount'] += $amount;
            $this->buckets[$bucketKey]['inflows']['outstanding_invoices']['count']++;
            $this->buckets[$bucketKey]['inflows']['outstanding_invoices']['weighted_amount'] += $amount * $weight;
            $this->buckets[$bucketKey]['_weights'][] = ['amount' => $amount, 'weight' => $weight];
        }
    }

    /**
     * @param array<int, \stdClass> $recurringInvoices
     */
    private function projectRecurringInvoices(array $recurringInvoices): void
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        foreach ($recurringInvoices as $ri) {
            $weight = $ri->auto_bill_enabled ? self::WEIGHT_AUTO_BILL : self::WEIGHT_NO_AUTO_BILL;
            $amount = $this->normalizeToCompanyCurrency((float) $ri->amount, (float) ($ri->exchange_rate ?? 1));
            $remainingCycles = (int) $ri->remaining_cycles;
            $frequencyId = (int) $ri->frequency_id;
            $maxIterations = ($frequencyId === RecurringInvoice::FREQUENCY_DAILY) ? self::MAX_DAILY_ITERATIONS : 1000;

            $date = Carbon::parse($ri->next_send_date);

            // Skip past dates without consuming remaining_cycles
            $skipped = 0;
            while ($date->lt($startDate) && $skipped < $maxIterations) {
                if ($remainingCycles !== -1 && $remainingCycles <= 0) {
                    break 2; // exhausted before reaching window
                }

                $date = self::advanceDateByFrequency($date, $frequencyId);

                if ($date === null) {
                    break 2;
                }

                $skipped++;

                if ($remainingCycles !== -1) {
                    $remainingCycles--;
                }
            }

            $iterations = 0;

            while ($date->lte($endDate) && $iterations < $maxIterations) {
                if ($remainingCycles !== -1 && $iterations >= $remainingCycles) {
                    break;
                }

                $bucketKey = $this->dateToBucketKey($date->format('Y-m-d'));

                if ($bucketKey !== null && isset($this->buckets[$bucketKey])) {
                    $this->buckets[$bucketKey]['inflows']['recurring_invoices']['amount'] += $amount;
                    $this->buckets[$bucketKey]['inflows']['recurring_invoices']['count']++;
                    $this->buckets[$bucketKey]['inflows']['recurring_invoices']['weighted_amount'] += $amount * $weight;
                    $this->buckets[$bucketKey]['_weights'][] = ['amount' => $amount, 'weight' => $weight];
                }

                $date = self::advanceDateByFrequency($date, $frequencyId);

                if ($date === null) {
                    break;
                }

                $iterations++;
            }
        }
    }

    /**
     * @param array<int, \stdClass> $recurringExpenses
     */
    private function projectRecurringExpenses(array $recurringExpenses): void
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        foreach ($recurringExpenses as $re) {
            $amount = $this->normalizeToCompanyCurrency(
                $this->computeExpenseWithTax($re),
                (float) ($re->exchange_rate ?? 1)
            );
            $remainingCycles = (int) $re->remaining_cycles;
            $frequencyId = (int) $re->frequency_id;
            $maxIterations = ($frequencyId === RecurringInvoice::FREQUENCY_DAILY) ? self::MAX_DAILY_ITERATIONS : 1000;

            $date = Carbon::parse($re->next_send_date);

            // Skip past dates without consuming remaining_cycles
            $skipped = 0;
            while ($date->lt($startDate) && $skipped < $maxIterations) {
                if ($remainingCycles !== -1 && $remainingCycles <= 0) {
                    break 2;
                }

                $date = self::advanceDateByFrequency($date, $frequencyId);

                if ($date === null) {
                    break 2;
                }

                $skipped++;

                if ($remainingCycles !== -1) {
                    $remainingCycles--;
                }
            }

            $iterations = 0;

            while ($date->lte($endDate) && $iterations < $maxIterations) {
                if ($remainingCycles !== -1 && $iterations >= $remainingCycles) {
                    break;
                }

                $bucketKey = $this->dateToBucketKey($date->format('Y-m-d'));

                if ($bucketKey !== null && isset($this->buckets[$bucketKey])) {
                    $this->buckets[$bucketKey]['outflows']['recurring_expenses']['amount'] += $amount;
                    $this->buckets[$bucketKey]['outflows']['recurring_expenses']['count']++;
                }

                $date = self::advanceDateByFrequency($date, $frequencyId);

                if ($date === null) {
                    break;
                }

                $iterations++;
            }
        }
    }

    /**
     * @param array<int, \stdClass> $expenses
     */
    private function projectUpcomingExpenses(array $expenses): void
    {
        foreach ($expenses as $expense) {
            $bucketKey = $this->dateToBucketKey($expense->date);

            if ($bucketKey === null || ! isset($this->buckets[$bucketKey])) {
                continue;
            }

            $amount = $this->normalizeToCompanyCurrency((float) $expense->amount, (float) ($expense->exchange_rate ?? 1));

            $this->buckets[$bucketKey]['outflows']['one_off_expenses']['amount'] += $amount;
            $this->buckets[$bucketKey]['outflows']['one_off_expenses']['count']++;
        }
    }

    /**
     * @param array<int, \stdClass> $quotes
     */
    private function projectOpenQuotes(array $quotes): void
    {
        foreach ($quotes as $quote) {
            $conversionRate = (float) ($quote->client_conversion_rate ?? 0);
            $avgConversionDays = (float) ($quote->client_avg_conversion_days ?? 0);

            // No conversion history: use defaults with reduced confidence
            if ($conversionRate <= 0) {
                $conversionRate = self::DEFAULT_CONVERSION_RATE * self::FALLBACK_CONFIDENCE_MULTIPLIER;
                $avgConversionDays = self::DEFAULT_CONVERSION_DAYS;
            }

            // Expected date is calculated from today (not quote creation date),
            // since these are currently open quotes awaiting a decision.
            $expectedDate = Carbon::now()->addDays((int) round($avgConversionDays))->format('Y-m-d');
            $bucketKey = $this->dateToBucketKey($expectedDate);

            if ($bucketKey === null || ! isset($this->buckets[$bucketKey])) {
                continue;
            }

            $amount = $this->normalizeToCompanyCurrency((float) $quote->amount, (float) ($quote->exchange_rate ?? 1));

            $this->buckets[$bucketKey]['inflows']['quote_pipeline']['amount'] += $amount;
            $this->buckets[$bucketKey]['inflows']['quote_pipeline']['count']++;
            $this->buckets[$bucketKey]['inflows']['quote_pipeline']['weighted_amount'] += $amount * $conversionRate;
            $this->buckets[$bucketKey]['_weights'][] = ['amount' => $amount, 'weight' => $conversionRate];
        }
    }

    private static function advanceDateByFrequency(Carbon $date, int $frequencyId): ?Carbon
    {
        if (! isset(self::FREQUENCY_INTERVALS[$frequencyId])) {
            return null;
        }

        [$method, $value] = self::FREQUENCY_INTERVALS[$frequencyId];

        return $date->copy()->{$method}($value);
    }

    private function computeExpenseWithTax(\stdClass $expense): float
    {
        $amount = (float) $expense->amount;

        if ($expense->uses_inclusive_taxes) {
            return $amount;
        }

        $taxFromRates = $amount * (((float) ($expense->tax_rate1 ?? 0) + (float) ($expense->tax_rate2 ?? 0) + (float) ($expense->tax_rate3 ?? 0)) / 100);
        $fixedTax = (float) ($expense->tax_amount1 ?? 0) + (float) ($expense->tax_amount2 ?? 0) + (float) ($expense->tax_amount3 ?? 0);

        return $amount + $taxFromRates + $fixedTax;
    }

    private function calculateBucketConfidence(array $weights): float
    {
        if (empty($weights)) {
            return 0.0;
        }

        $totalAmount = 0.0;
        $weightedSum = 0.0;

        foreach ($weights as $w) {
            $totalAmount += abs($w['amount']);
            $weightedSum += abs($w['amount']) * $w['weight'];
        }

        if ($totalAmount == 0) {
            return 0.0;
        }

        return round($weightedSum / $totalAmount, 4);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(): array
    {
        $totalInflows = 0.0;
        $weightedInflows = 0.0;
        $totalOutflows = 0.0;

        $resultBuckets = [];

        foreach ($this->buckets as $key => &$bucket) {
            $inflowTotal = $bucket['inflows']['outstanding_invoices']['amount']
                + $bucket['inflows']['recurring_invoices']['amount']
                + $bucket['inflows']['quote_pipeline']['amount'];

            $inflowWeighted = $bucket['inflows']['outstanding_invoices']['weighted_amount']
                + $bucket['inflows']['recurring_invoices']['weighted_amount']
                + $bucket['inflows']['quote_pipeline']['weighted_amount'];

            $outflowTotal = $bucket['outflows']['recurring_expenses']['amount']
                + $bucket['outflows']['one_off_expenses']['amount'];

            $bucket['inflows']['total'] = round($inflowTotal, 2);
            $bucket['inflows']['weighted_total'] = round($inflowWeighted, 2);
            $bucket['outflows']['total'] = round($outflowTotal, 2);
            $bucket['net'] = round($inflowTotal - $outflowTotal, 2);
            $bucket['weighted_net'] = round($inflowWeighted - $outflowTotal, 2);
            $bucket['confidence'] = $this->calculateBucketConfidence($bucket['_weights']);

            $totalInflows += $inflowTotal;
            $weightedInflows += $inflowWeighted;
            $totalOutflows += $outflowTotal;

            unset($bucket['_weights']);
            $resultBuckets[] = $bucket;
        }

        return [
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'bucket_type' => $this->bucket_type,
            'buckets' => $resultBuckets,
            'totals' => [
                'total_inflows' => round($totalInflows, 2),
                'weighted_inflows' => round($weightedInflows, 2),
                'total_outflows' => round($totalOutflows, 2),
                'net' => round($totalInflows - $totalOutflows, 2),
                'weighted_net' => round($weightedInflows - $totalOutflows, 2),
            ],
        ];
    }
}
