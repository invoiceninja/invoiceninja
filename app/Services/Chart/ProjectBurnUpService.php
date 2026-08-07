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
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class ProjectBurnUpService
{
    /** @var array<int, string> */
    private const FINANCIAL_FIELDS = [
        'task_value',
        'invoiced_amount',
        'paid_to_date',
        'outstanding_amount',
        'expense_amount',
        'net_invoiced_amount',
        'net_paid_amount',
        'budgeted_amount',
        'ideal_amount',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $buckets = [];

    private Carbon $start_date;

    private Carbon $end_date;

    public function __construct(
        private Company $company,
        private User $user,
        private bool $is_admin,
        private bool $include_drafts = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(Project $project, string $start_date, string $end_date, string $bucket_type = 'daily'): array
    {
        $this->start_date = Carbon::parse($start_date)->startOfDay();
        $this->end_date = Carbon::parse($end_date)->endOfDay();
        $this->buckets = [];

        $project->loadMissing('client');

        $this->buildBucketGrid($bucket_type);
        $this->applyTaskLogs($project);
        $this->applyInvoices($project);
        $this->applyExpenses($project);

        return $this->buildResponse($project, $bucket_type);
    }

    private function buildBucketGrid(string $bucket_type): void
    {
        $current = $this->start_date->copy();

        while ($current->lte($this->end_date)) {
            if ($bucket_type === 'weekly') {
                $key = $current->format('o-\\WW');
                $period_start = $current->copy()->startOfWeek();
                $period_end = $current->copy()->endOfWeek();
                $current->addWeek();
            } elseif ($bucket_type === 'monthly') {
                $key = $current->format('Y-m');
                $period_start = $current->copy()->startOfMonth();
                $period_end = $current->copy()->endOfMonth();
                $current->addMonthNoOverflow();
            } else {
                $key = $current->format('Y-m-d');
                $period_start = $current->copy()->startOfDay();
                $period_end = $current->copy()->endOfDay();
                $current->addDay();
            }

            $this->buckets[$key] = [
                'period' => $key,
                'date' => $period_start->format('Y-m-d'),
                'period_start' => $period_start->format('Y-m-d'),
                'period_end' => $period_end->format('Y-m-d'),
                'logged_hours' => 0.0,
                'billable_hours' => 0.0,
                'task_value' => 0.0,
                'invoiced_amount' => 0.0,
                'paid_to_date' => 0.0,
                'outstanding_amount' => 0.0,
                'expense_amount' => 0.0,
                'net_invoiced_amount' => 0.0,
                'net_paid_amount' => 0.0,
                'task_log_count' => 0,
                'invoice_count' => 0,
                'expense_count' => 0,
            ];
        }
    }

    private function applyTaskLogs(Project $project): void
    {
        Task::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('project_id', $project->id)
            ->where('is_deleted', 0)
            ->when(! $this->is_admin, function ($query): void {
                $query->where('user_id', $this->user->id);
            })
            ->orderBy('calculated_start_date')
            ->cursor()
            ->each(function (Task $task) use ($project): void {
                $time_log = json_decode($task->time_log ?? '[]', true);

                if (! is_array($time_log)) {
                    return;
                }

                foreach ($time_log as $log) {
                    if (! is_array($log) || ! isset($log[0])) {
                        continue;
                    }

                    $this->applyTaskLog($task, $project, $log);
                }
            });
    }

    /**
     * @param array<int, mixed> $log
     */
    private function applyTaskLog(Task $task, Project $project, array $log): void
    {
        $start_timestamp = (int) $log[0];
        $end_timestamp = isset($log[1]) && (int) $log[1] > 0 ? (int) $log[1] : time();

        if ($start_timestamp <= 0 || $end_timestamp <= $start_timestamp) {
            return;
        }

        $offset = $this->company->utc_offset();
        $start = Carbon::createFromTimestamp($start_timestamp)->addSeconds($offset);
        $end = Carbon::createFromTimestamp($end_timestamp)->addSeconds($offset);
        $billable = ! isset($log[3]) || (bool) $log[3];
        $rate = $this->taskRate($task, $project);
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $bucket_key = $this->dateToBucketKey($cursor);
            $bucket_boundary = $this->nextBucketBoundary($cursor);
            $segment_end = $end->lt($bucket_boundary) ? $end->copy() : $bucket_boundary;
            $seconds = max($segment_end->getTimestamp() - $cursor->getTimestamp(), 0);

            if ($seconds > 0 && $bucket_key !== null && isset($this->buckets[$bucket_key])) {
                $hours = $seconds / 3600;

                $this->buckets[$bucket_key]['logged_hours'] += $hours;
                $this->buckets[$bucket_key]['task_log_count']++;

                if ($billable) {
                    $this->buckets[$bucket_key]['billable_hours'] += $hours;
                    $this->buckets[$bucket_key]['task_value'] += $hours * $rate;
                }
            }

            if ($seconds === 0) {
                $cursor->addSecond();
            } else {
                $cursor = $segment_end->copy();
            }
        }
    }

    private function applyInvoices(Project $project): void
    {
        $statuses = $this->include_drafts
            ? [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID]
            : [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];

        Invoice::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('project_id', $project->id)
            ->where('is_deleted', 0)
            ->whereIn('status_id', $statuses)
            ->whereBetween('date', [$this->start_date->format('Y-m-d'), $this->end_date->format('Y-m-d')])
            ->when(! $this->is_admin, function ($query): void {
                $query->where('user_id', $this->user->id);
            })
            ->orderBy('date')
            ->cursor()
            ->each(function (Invoice $invoice): void {
                $bucket_key = $this->dateToBucketKey(Carbon::parse($invoice->date));

                if ($bucket_key === null || ! isset($this->buckets[$bucket_key])) {
                    return;
                }

                $amount = (float) $invoice->amount;
                $paid_to_date = (float) $invoice->paid_to_date;

                $this->buckets[$bucket_key]['invoiced_amount'] += $amount;
                $this->buckets[$bucket_key]['paid_to_date'] += $paid_to_date;
                $this->buckets[$bucket_key]['outstanding_amount'] += max($amount - $paid_to_date, 0);
                $this->buckets[$bucket_key]['invoice_count']++;
            });
    }

    private function applyExpenses(Project $project): void
    {
        Expense::withTrashed()
            ->where('company_id', $this->company->id)
            ->where('project_id', $project->id)
            ->where('is_deleted', 0)
            ->whereBetween('date', [$this->start_date->format('Y-m-d'), $this->end_date->format('Y-m-d')])
            ->when(! $this->is_admin, function ($query): void {
                $query->where('user_id', $this->user->id);
            })
            ->orderBy('date')
            ->cursor()
            ->each(function (Expense $expense): void {
                $bucket_key = $this->dateToBucketKey(Carbon::parse($expense->date));

                if ($bucket_key === null || ! isset($this->buckets[$bucket_key])) {
                    return;
                }

                $this->buckets[$bucket_key]['expense_amount'] += $this->expenseAmount($expense);
                $this->buckets[$bucket_key]['expense_count']++;
            });
    }

    private function taskRate(Task $task, Project $project): float
    {
        if ((float) $task->rate > 0) {
            return (float) $task->rate;
        }

        if ((float) $project->task_rate > 0) {
            return (float) $project->task_rate;
        }

        if ($project->client) {
            return (float) $project->client->getSetting('default_task_rate');
        }

        return (float) ($this->company->settings->default_task_rate ?? 0);
    }

    private function expenseAmount(Expense $expense): float
    {
        $amount = (float) $expense->foreign_amount > 0 ? (float) $expense->foreign_amount : (float) $expense->amount;

        if ($expense->uses_inclusive_taxes) {
            return $amount;
        }

        $tax_from_rates = $amount * (
            (
                (float) ($expense->tax_rate1 ?? 0)
                + (float) ($expense->tax_rate2 ?? 0)
                + (float) ($expense->tax_rate3 ?? 0)
            ) / 100
        );

        return $amount
            + $tax_from_rates
            + (float) ($expense->tax_amount1 ?? 0)
            + (float) ($expense->tax_amount2 ?? 0)
            + (float) ($expense->tax_amount3 ?? 0);
    }

    private function dateToBucketKey(Carbon $date): ?string
    {
        if ($date->lt($this->start_date) || $date->gt($this->end_date)) {
            return null;
        }

        $first_bucket = reset($this->buckets);
        $period = $first_bucket['period'] ?? '';

        if (preg_match('/^\\d{4}-W\\d{2}$/', (string) $period) === 1) {
            return $date->format('o-\\WW');
        }

        if (preg_match('/^\\d{4}-\\d{2}$/', (string) $period) === 1) {
            return $date->format('Y-m');
        }

        return $date->format('Y-m-d');
    }

    private function nextBucketBoundary(Carbon $date): Carbon
    {
        $first_bucket = reset($this->buckets);
        $period = $first_bucket['period'] ?? '';

        if (preg_match('/^\\d{4}-W\\d{2}$/', (string) $period) === 1) {
            return $date->copy()->startOfWeek()->addWeek();
        }

        if (preg_match('/^\\d{4}-\\d{2}$/', (string) $period) === 1) {
            return $date->copy()->startOfMonth()->addMonthNoOverflow();
        }

        return $date->copy()->startOfDay()->addDay();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(Project $project, string $bucket_type): array
    {
        $budgeted_hours = (float) $project->budgeted_hours;
        $task_rate = (float) $project->task_rate;
        $budgeted_amount = $this->budgetedAmount($project, $budgeted_hours, $task_rate);
        $project_start = $project->created_at ? Carbon::parse($project->created_at)->startOfDay() : $this->start_date->copy();
        $project_due = $project->due_date ? Carbon::parse($project->due_date)->endOfDay() : null;
        $currency_id = (string) ($project->client?->getSetting('currency_id') ?? $this->company->settings->currency_id);

        $series = [];
        $totals = [
            'logged_hours' => 0.0,
            'billable_hours' => 0.0,
            'task_value' => 0.0,
            'invoiced_amount' => 0.0,
            'paid_to_date' => 0.0,
            'outstanding_amount' => 0.0,
            'expense_amount' => 0.0,
            'net_invoiced_amount' => 0.0,
            'net_paid_amount' => 0.0,
        ];

        foreach ($this->buckets as $bucket) {
            $bucket['net_invoiced_amount'] = $bucket['invoiced_amount'] - $bucket['expense_amount'];
            $bucket['net_paid_amount'] = $bucket['paid_to_date'] - $bucket['expense_amount'];

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + (float) $bucket[$key];
                $bucket["cumulative_{$key}"] = $totals[$key];
            }

            $bucket['budgeted_hours'] = $budgeted_hours;
            $bucket['budgeted_amount'] = $budgeted_amount;
            $bucket['ideal_hours'] = $this->idealHours($project_start, $project_due, Carbon::parse($bucket['period_end']), $budgeted_hours);
            $bucket['ideal_amount'] = $this->idealAmount($bucket['ideal_hours'], $budgeted_hours, $budgeted_amount);

            $rounded_bucket = $this->roundBucket($bucket);

            if (! $this->is_admin) {
                $rounded_bucket = $this->removeFinancialFields($rounded_bucket);
            }

            $series[] = $rounded_bucket;
        }

        $project_data = [
            'id' => $project->hashed_id,
            'name' => $project->name,
            'client_id' => $project->client?->hashed_id,
            'start_date' => $project_start->format('Y-m-d'),
            'due_date' => $project_due?->format('Y-m-d'),
            'budgeted_hours' => round($budgeted_hours, 2),
        ];

        $markers = [
            'due_date' => $project_due?->format('Y-m-d'),
            'budgeted_hours' => round($budgeted_hours, 2),
        ];

        if ($this->is_admin) {
            $project_data = array_merge($project_data, [
                'task_rate' => round($task_rate, 2),
                'budgeted_amount' => round($budgeted_amount, 2),
                'currency_id' => $currency_id,
            ]);

            $markers['budgeted_amount'] = round($budgeted_amount, 2);
        }

        $rounded_totals = $this->roundTotals($totals);

        if (! $this->is_admin) {
            $rounded_totals = $this->removeFinancialFields($rounded_totals);
        }

        return [
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'bucket_type' => $bucket_type,
            'project' => $project_data,
            'markers' => $markers,
            'series' => $series,
            'totals' => $rounded_totals,
            'metadata' => [
                'can_view_financials' => $this->is_admin,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function removeFinancialFields(array $data): array
    {
        foreach (self::FINANCIAL_FIELDS as $field) {
            unset($data[$field], $data["cumulative_{$field}"]);
        }

        return $data;
    }

    private function budgetedAmount(Project $project, float $budgeted_hours, float $task_rate): float
    {
        $budgeted_amount = (float) $project->budgeted_amount;

        return $budgeted_amount > 0 ? $budgeted_amount : $budgeted_hours * $task_rate;
    }

    private function idealAmount(float $ideal_hours, float $budgeted_hours, float $budgeted_amount): float
    {
        if ($budgeted_hours <= 0 || $budgeted_amount <= 0) {
            return 0.0;
        }

        return $budgeted_amount * min($ideal_hours / $budgeted_hours, 1);
    }

    private function idealHours(Carbon $project_start, ?Carbon $project_due, Carbon $period_end, float $budgeted_hours): float
    {
        if (! $project_due || $budgeted_hours <= 0) {
            return 0.0;
        }

        if ($period_end->lt($project_start)) {
            return 0.0;
        }

        if ($period_end->gte($project_due)) {
            return $budgeted_hours;
        }

        $total_seconds = max($project_due->getTimestamp() - $project_start->getTimestamp(), 1);
        $elapsed_seconds = max($period_end->getTimestamp() - $project_start->getTimestamp(), 0);

        return $budgeted_hours * min($elapsed_seconds / $total_seconds, 1);
    }

    /**
     * @param array<string, mixed> $bucket
     * @return array<string, mixed>
     */
    private function roundBucket(array $bucket): array
    {
        foreach ($bucket as $key => $value) {
            if (is_float($value)) {
                $bucket[$key] = round($value, 2);
            }
        }

        return $bucket;
    }

    /**
     * @param array<string, float> $totals
     * @return array<string, float>
     */
    private function roundTotals(array $totals): array
    {
        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return $totals;
    }
}
