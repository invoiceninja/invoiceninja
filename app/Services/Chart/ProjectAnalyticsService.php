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
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;

class ProjectAnalyticsService
{
    use MakesHash;

    public function __construct(
        private Company $company,
        private User $user,
        private bool $isAdmin,
        private bool $includeDrafts = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(?Project $project = null): array
    {
        $projects = $this->projects($project);
        $projectsById = $projects->keyBy('id');
        $projectIds = $projects->pluck('id')->map(fn ($id) => (int) $id);
        $tasks = $this->tasks($projectIds);
        $invoices = $this->invoices($projectIds, $tasks);
        $expenses = $this->expenses($projectIds);

        $taskMetrics = $this->buildTaskMetrics($tasks, $projectsById);
        $invoiceMetrics = $this->buildInvoiceMetrics($invoices, $tasks, $projectsById);
        $expenseMetrics = $this->buildExpenseMetrics($expenses);

        $snapshots = $projects
            ->map(fn (Project $project) => $this->snapshot(
                $project,
                $taskMetrics[(int) $project->id] ?? $this->emptyTaskMetrics(),
                $invoiceMetrics[(int) $project->id] ?? $this->emptyInvoiceMetrics(),
                $expenseMetrics[(int) $project->id] ?? $this->emptyExpenseMetrics(),
            ))
            ->values();

        if (! $this->isAdmin) {
            return [
                'budget_summary' => [],
                'budget_vs_actual' => [],
                'estimated_vs_logged_hours' => [],
                'invoice_progress' => [],
                'forecast_completion' => $this->forecastCompletion($snapshots),
                'project_health' => [],
                'team_contribution' => [],
                'time_distribution' => [],
                'unbilled_hours' => [],
                'velocity_trend' => [],
                'timeline_variance' => [],
                'expense_breakdown' => [],
                'cumulative_spend' => [],
                'profitability' => [],
                'metadata' => $this->metadata($snapshots),
            ];
        }

        return [
            'budget_summary' => $this->budgetSummary($snapshots),
            'budget_vs_actual' => $this->budgetVsActual($snapshots),
            'estimated_vs_logged_hours' => $this->estimatedVsLoggedHours($snapshots),
            'invoice_progress' => $this->invoiceProgress($snapshots),
            'forecast_completion' => $this->forecastCompletion($snapshots),
            'project_health' => $this->projectHealth($snapshots),
            'team_contribution' => $this->teamContribution($snapshots),
            'time_distribution' => $this->timeDistribution($snapshots),
            'unbilled_hours' => $this->unbilledHours($snapshots),
            'velocity_trend' => $this->velocityTrend($snapshots),
            'timeline_variance' => $this->timelineVariance($snapshots),
            'expense_breakdown' => $this->expenseBreakdown($snapshots),
            'cumulative_spend' => $this->cumulativeSpend($snapshots),
            'profitability' => $this->profitability($snapshots),
            'metadata' => $this->metadata($snapshots),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<string, mixed>
     */
    private function metadata(Collection $snapshots): array
    {
        return [
            'project_count' => $snapshots->count(),
            'include_drafts' => $this->includeDrafts,
            'generated_at' => now()->toDateTimeString(),
            'can_view_financials' => $this->isAdmin,
        ];
    }

    /**
     * @return Collection<int, Project>
     */
    private function projects(?Project $project = null): Collection
    {
        return Project::withTrashed()
            ->without('documents')
            ->with('client')
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->when($project, function ($query) use ($project): void {
                $query->whereKey($project->id);
            })
            ->when(! $project && ! $this->isAdmin, function ($query): void {
                $query->where('user_id', $this->user->id);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param Collection<int, int> $projectIds
     * @return Collection<int, Task>
     */
    private function tasks(Collection $projectIds): Collection
    {
        if ($projectIds->isEmpty()) {
            return collect();
        }

        return Task::withTrashed()
            ->with(['status', 'user'])
            ->where('company_id', $this->company->id)
            ->whereIn('project_id', $projectIds)
            ->where('is_deleted', 0)
            ->when(! $this->isAdmin, function ($query): void {
                $query->where('user_id', $this->user->id);
            })
            ->get();
    }

    /**
     * @param Collection<int, int> $projectIds
     * @param Collection<int, Task> $tasks
     * @return Collection<int, Invoice>
     */
    private function invoices(Collection $projectIds, Collection $tasks): Collection
    {
        if ($projectIds->isEmpty()) {
            return collect();
        }

        $taskInvoiceIds = $tasks
            ->pluck('invoice_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $statuses = $this->includeDrafts
            ? [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID]
            : [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];

        return Invoice::withTrashed()
            ->where('company_id', $this->company->id)
            ->where(function ($query) use ($projectIds, $taskInvoiceIds): void {
                $query->whereIn('project_id', $projectIds);

                if ($taskInvoiceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $taskInvoiceIds);
                }
            })
            ->where('is_deleted', 0)
            ->whereIn('status_id', $statuses)
            ->when(! $this->isAdmin, function ($query): void {
                $query->where('user_id', $this->user->id);
            })
            ->get();
    }

    /**
     * @param Collection<int, int> $projectIds
     * @return Collection<int, Expense>
     */
    private function expenses(Collection $projectIds): Collection
    {
        if ($projectIds->isEmpty()) {
            return collect();
        }

        return Expense::withTrashed()
            ->with('category')
            ->where('company_id', $this->company->id)
            ->whereIn('project_id', $projectIds)
            ->where('is_deleted', 0)
            ->when(! $this->isAdmin, function ($query): void {
                $query->where('user_id', $this->user->id);
            })
            ->get();
    }

    /**
     * @param Collection<int, Task> $tasks
     * @param Collection<int, Project> $projectsById
     * @return array<int, array<string, mixed>>
     */
    private function buildTaskMetrics(Collection $tasks, Collection $projectsById): array
    {
        $metrics = [];

        foreach ($tasks as $task) {
            $projectId = (int) $task->project_id;
            $project = $projectsById->get($projectId);

            if (! $project) {
                continue;
            }

            if (! isset($metrics[$projectId])) {
                $metrics[$projectId] = $this->emptyTaskMetrics();
            }

            $logs = $this->taskLogEntries($task, $project);
            $taskLoggedHours = array_sum(array_column($logs, 'hours'));
            $taskBillableHours = array_sum(array_column($logs, 'billable_hours'));
            $taskValue = array_sum(array_column($logs, 'value'));
            $isCompleted = $this->isCompletedTask($task);
            $isInvoiced = $task->invoice_id !== null;

            $metrics[$projectId]['total_tasks']++;
            $metrics[$projectId]['completed_tasks'] += $isCompleted ? 1 : 0;
            $metrics[$projectId]['invoiced_tasks'] += $isInvoiced ? 1 : 0;
            $metrics[$projectId]['uninvoiced_tasks'] += $isInvoiced ? 0 : 1;
            $metrics[$projectId]['running_tasks'] += $task->is_running ? 1 : 0;
            $metrics[$projectId]['logged_hours'] += $taskLoggedHours;
            $metrics[$projectId]['billable_hours'] += $taskBillableHours;
            $metrics[$projectId]['billable_value'] += $taskValue;

            if (! $isInvoiced) {
                $metrics[$projectId]['unbilled_hours'] += $taskBillableHours;
                $metrics[$projectId]['unbilled_value'] += $taskValue;
            }

            foreach ($logs as $log) {
                $date = $log['date'];

                if (! isset($metrics[$projectId]['velocity'][$date])) {
                    $metrics[$projectId]['velocity'][$date] = [
                        'period' => $date,
                        'hours' => 0.0,
                        'billable_hours' => 0.0,
                        'task_value' => 0.0,
                        'task_log_count' => 0,
                    ];
                }

                $metrics[$projectId]['velocity'][$date]['hours'] += $log['hours'];
                $metrics[$projectId]['velocity'][$date]['billable_hours'] += $log['billable_hours'];
                $metrics[$projectId]['velocity'][$date]['task_value'] += $log['value'];
                $metrics[$projectId]['velocity'][$date]['task_log_count']++;
            }

            if ($taskLoggedHours > 0) {
                $userId = (int) $task->user_id;

                if (! isset($metrics[$projectId]['team'][$userId])) {
                    $metrics[$projectId]['team'][$userId] = [
                        'user_id' => $this->encodedPrimaryKey($userId),
                        'user_name' => $this->userName($task),
                        'logged_hours' => 0.0,
                        'billable_hours' => 0.0,
                        'billable_value' => 0.0,
                        'task_count' => 0,
                    ];
                }

                $metrics[$projectId]['team'][$userId]['logged_hours'] += $taskLoggedHours;
                $metrics[$projectId]['team'][$userId]['billable_hours'] += $taskBillableHours;
                $metrics[$projectId]['team'][$userId]['billable_value'] += $taskValue;
                $metrics[$projectId]['team'][$userId]['task_count']++;
            }

            $metrics[$projectId]['tasks'][] = [
                'task_id' => $this->encodedPrimaryKey((int) $task->id),
                'task_number' => (string) $task->number,
                'description' => (string) $task->description,
                'status_id' => $task->status_id ? $this->encodedPrimaryKey((int) $task->status_id) : null,
                'status_name' => (string) ($task->status->name ?? ''),
                'status_order' => $this->taskStatusOrder($task),
                'logged_hours' => round($taskLoggedHours, 2),
                'billable_hours' => round($taskBillableHours, 2),
                'billable_value' => round($taskValue, 2),
                'is_completed' => $isCompleted,
                'is_invoiced' => $isInvoiced,
            ];

        }

        return $metrics;
    }

    /**
     * @param Collection<int, Invoice> $invoices
     * @param Collection<int, Task> $tasks
     * @param Collection<int, Project> $projectsById
     * @return array<int, array<string, mixed>>
     */
    private function buildInvoiceMetrics(Collection $invoices, Collection $tasks, Collection $projectsById): array
    {
        $metrics = [];
        $tasksByInvoiceId = $tasks
            ->filter(fn (Task $task): bool => $task->invoice_id !== null)
            ->groupBy(fn (Task $task): int => (int) $task->invoice_id);

        foreach ($invoices as $invoice) {
            $projectId = (int) $invoice->project_id;

            if ($projectId && $projectsById->has($projectId)) {
                $this->addInvoiceMetric($metrics, $projectId, $invoice, 1.0, true);
                continue;
            }

            $invoiceTasks = $tasksByInvoiceId->get((int) $invoice->id, collect());

            foreach ($this->taskInvoiceAllocations($invoiceTasks, $projectsById) as $taskProjectId => $share) {
                $this->addInvoiceMetric($metrics, $taskProjectId, $invoice, $share, false);
            }

        }

        return $metrics;
    }

    /**
     * @param array<int, array<string, mixed>> $metrics
     */
    private function addInvoiceMetric(array &$metrics, int $projectId, Invoice $invoice, float $share, bool $projectInvoice): void
    {
        if ($share <= 0) {
            return;
        }

        if (! isset($metrics[$projectId])) {
            $metrics[$projectId] = $this->emptyInvoiceMetrics();
        }

        $invoiceAmount = (float) $invoice->amount;
        $invoicePaid = (float) $invoice->paid_to_date;
        $invoiceOutstanding = max((float) $invoice->balance, $invoiceAmount - $invoicePaid, 0);

        $metrics[$projectId]['invoice_count']++;
        $metrics[$projectId]['invoiced_amount'] += $invoiceAmount * $share;
        $metrics[$projectId]['paid_amount'] += $invoicePaid * $share;
        $metrics[$projectId]['outstanding_amount'] += $invoiceOutstanding * $share;
        $metrics[$projectId]['paid_invoice_count'] += $invoiceOutstanding <= 0.0 ? 1 : 0;
        $metrics[$projectId]['outstanding_invoice_count'] += $invoiceOutstanding > 0.0 ? 1 : 0;
        $metrics[$projectId][$projectInvoice ? 'project_invoice_count' : 'task_invoice_count']++;
    }

    /**
     * @param Collection<int, Task> $invoiceTasks
     * @param Collection<int, Project> $projectsById
     * @return array<int, float>
     */
    private function taskInvoiceAllocations(Collection $invoiceTasks, Collection $projectsById): array
    {
        if ($invoiceTasks->isEmpty()) {
            return [];
        }

        $projectValues = [];
        $projectTaskCounts = [];

        foreach ($invoiceTasks as $task) {
            $projectId = (int) $task->project_id;
            $project = $projectsById->get($projectId);

            if (! $project) {
                continue;
            }

            $logs = $this->taskLogEntries($task, $project);
            $projectValues[$projectId] = ($projectValues[$projectId] ?? 0.0) + array_sum(array_column($logs, 'value'));
            $projectTaskCounts[$projectId] = ($projectTaskCounts[$projectId] ?? 0) + 1;
        }

        $totalValue = array_sum($projectValues);
        $totalTaskCount = array_sum($projectTaskCounts);
        $allocations = [];

        foreach ($projectTaskCounts as $projectId => $taskCount) {
            $allocations[$projectId] = $totalValue > 0
                ? ($projectValues[$projectId] ?? 0.0) / $totalValue
                : $taskCount / max($totalTaskCount, 1);
        }

        return $allocations;
    }

    /**
     * @param Collection<int, Expense> $expenses
     * @return array<int, array<string, mixed>>
     */
    private function buildExpenseMetrics(Collection $expenses): array
    {
        $metrics = [];

        foreach ($expenses as $expense) {
            $projectId = (int) $expense->project_id;

            if (! isset($metrics[$projectId])) {
                $metrics[$projectId] = $this->emptyExpenseMetrics();
            }

            $amount = $this->expenseAmount($expense);
            $categoryId = $expense->category_id ? (int) $expense->category_id : 0;
            $categoryName = (string) ($expense->category->name ?? 'Uncategorized');
            $date = $this->carbonDate($expense->date ?? $expense->updated_at);

            $metrics[$projectId]['expense_count']++;
            $metrics[$projectId]['expense_amount'] += $amount;

            if (! isset($metrics[$projectId]['categories'][$categoryId])) {
                $metrics[$projectId]['categories'][$categoryId] = [
                    'category_id' => $categoryId ? $this->encodedPrimaryKey($categoryId) : null,
                    'category_name' => $categoryName,
                    'expense_count' => 0,
                    'expense_amount' => 0.0,
                ];
            }

            $metrics[$projectId]['categories'][$categoryId]['expense_count']++;
            $metrics[$projectId]['categories'][$categoryId]['expense_amount'] += $amount;

            if ($date) {
                $period = $date->format('Y-m-d');

                if (! isset($metrics[$projectId]['daily'][$period])) {
                    $metrics[$projectId]['daily'][$period] = [
                        'period' => $period,
                        'expense_amount' => 0.0,
                        'expense_count' => 0,
                    ];
                }

                $metrics[$projectId]['daily'][$period]['expense_amount'] += $amount;
                $metrics[$projectId]['daily'][$period]['expense_count']++;
            }
        }

        return $metrics;
    }

    /**
     * @param array<string, mixed> $taskMetrics
     * @param array<string, mixed> $invoiceMetrics
     * @param array<string, mixed> $expenseMetrics
     * @return array<string, mixed>
     */
    private function snapshot(Project $project, array $taskMetrics, array $invoiceMetrics, array $expenseMetrics): array
    {
        $budgetedHours = (float) $project->budgeted_hours;
        $loggedHours = (float) $project->current_hours;
        $taskRate = (float) $project->task_rate;
        $budgetedAmount = $this->budgetedAmount($project, $budgetedHours, $taskRate);
        $laborValue = (float) $taskMetrics['billable_value'] > 0
            ? (float) $taskMetrics['billable_value']
            : $loggedHours * $taskRate;
        $expenseAmount = (float) $expenseMetrics['expense_amount'];
        $actualAmount = $laborValue + $expenseAmount;
        $invoicedAmount = (float) $invoiceMetrics['invoiced_amount'];
        $paidAmount = (float) $invoiceMetrics['paid_amount'];
        $outstandingAmount = (float) $invoiceMetrics['outstanding_amount'];
        $utilization = $budgetedHours > 0 ? $loggedHours / $budgetedHours : 0.0;
        $hoursRemaining = max($budgetedHours - $loggedHours, 0.0);
        $budgetUtilization = $budgetedAmount > 0 ? $actualAmount / $budgetedAmount : 0.0;
        $completionRatio = $this->completionRatio($budgetedHours, $loggedHours, (int) $taskMetrics['total_tasks'], (int) $taskMetrics['completed_tasks']);
        $averageDailyVelocity = $this->averageDailyVelocity($taskMetrics, $project, $loggedHours);
        $forecastDate = $this->forecastFinishDate($hoursRemaining, $averageDailyVelocity);
        $dueDate = $this->carbonDate($project->due_date);
        $projectStart = $this->carbonDate($project->created_at) ?? now()->startOfDay();
        $scheduleVarianceDays = $this->scheduleVarianceDays($forecastDate, $dueDate);
        $idealProgressRatio = $this->idealProgressRatio($projectStart, $dueDate);
        $profitAmount = $invoicedAmount - $expenseAmount;
        $marginRatio = $invoicedAmount > 0 ? $profitAmount / $invoicedAmount : 0.0;
        $unbilledValue = $this->unbilledValue($laborValue, $invoicedAmount, $taskMetrics, $invoiceMetrics);
        $health = $this->healthScore(
            $budgetUtilization,
            $scheduleVarianceDays,
            $marginRatio,
            $laborValue,
            $unbilledValue,
            $invoicedAmount,
            $outstandingAmount,
            $completionRatio,
            $dueDate,
        );

        return [
            'project_id' => $this->encodedPrimaryKey((int) $project->id),
            'project_hash' => $project->hashed_id,
            'project_name' => (string) $project->name,
            'client_id' => $project->client_id ? $this->encodedPrimaryKey((int) $project->client_id) : null,
            'client_hash' => $project->client?->hashed_id,
            'currency_id' => (string) ($project->client?->getSetting('currency_id') ?? $this->company->settings->currency_id),
            'budgeted_hours' => $budgetedHours,
            'logged_hours' => $loggedHours,
            'billable_hours' => (float) $taskMetrics['billable_hours'],
            'hours_remaining' => $hoursRemaining,
            'task_rate' => $taskRate,
            'budgeted_amount' => $budgetedAmount,
            'labor_value' => $laborValue,
            'expense_amount' => $expenseAmount,
            'actual_amount' => $actualAmount,
            'remaining_budget' => max($budgetedAmount - $actualAmount, 0.0),
            'utilization' => $utilization,
            'budget_utilization' => $budgetUtilization,
            'completion_ratio' => $completionRatio,
            'completion_percentage' => $completionRatio * 100,
            'invoiced_amount' => $invoicedAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'unbilled_hours' => (float) $taskMetrics['unbilled_hours'],
            'unbilled_value' => $unbilledValue,
            'invoice_count' => (int) $invoiceMetrics['invoice_count'],
            'paid_invoice_count' => (int) $invoiceMetrics['paid_invoice_count'],
            'outstanding_invoice_count' => (int) $invoiceMetrics['outstanding_invoice_count'],
            'total_tasks' => (int) $taskMetrics['total_tasks'],
            'completed_tasks' => (int) $taskMetrics['completed_tasks'],
            'invoiced_tasks' => (int) $taskMetrics['invoiced_tasks'],
            'uninvoiced_tasks' => (int) $taskMetrics['uninvoiced_tasks'],
            'running_tasks' => (int) $taskMetrics['running_tasks'],
            'average_daily_velocity' => $averageDailyVelocity,
            'forecast_finish_date' => $forecastDate,
            'due_date' => $dueDate?->format('Y-m-d'),
            'schedule_variance_days' => $scheduleVarianceDays,
            'ideal_progress_percentage' => $idealProgressRatio !== null ? $idealProgressRatio * 100 : null,
            'profit_amount' => $profitAmount,
            'margin_ratio' => $marginRatio,
            'health_score' => $health['score'],
            'health_status' => $health['status'],
            'health_indicators' => $health['indicators'],
            'team' => $this->roundRows(array_values($taskMetrics['team'])),
            'tasks' => $taskMetrics['tasks'],
            'velocity' => $this->seriesRows($taskMetrics['velocity']),
            'expense_categories' => $this->roundRows(array_values($expenseMetrics['categories'])),
            'expense_daily' => $this->seriesRows($expenseMetrics['daily']),
            'cumulative_spend' => $this->buildCumulativeSpend($taskMetrics, $expenseMetrics),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function budgetSummary(Collection $snapshots): array
    {
        return $snapshots
            ->sortByDesc('utilization')
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'client_id' => $row['client_id'],
                'budgeted_hours' => round($row['budgeted_hours'], 2),
                'current_hours' => round($row['logged_hours'], 2),
                'task_rate' => round($row['task_rate'], 2),
                'due_date' => $row['due_date'],
                'total_tasks' => $row['total_tasks'],
                'invoiced_tasks' => $row['invoiced_tasks'],
                'uninvoiced_tasks' => $row['uninvoiced_tasks'],
                'running_tasks' => $row['running_tasks'],
                'utilization' => round($row['utilization'], 4),
                'hours_remaining' => round($row['hours_remaining'], 2),
                'budgeted_amount' => round($row['budgeted_amount'], 2),
                'actual_amount' => round($row['actual_amount'], 2),
                'remaining_budget' => round($row['remaining_budget'], 2),
                'budget_utilization' => round($row['budget_utilization'], 4),
                'currency_id' => $row['currency_id'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function budgetVsActual(Collection $snapshots): array
    {
        return $snapshots
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'budgeted_amount' => round($row['budgeted_amount'], 2),
                'actual_amount' => round($row['actual_amount'], 2),
                'labor_value' => round($row['labor_value'], 2),
                'expense_amount' => round($row['expense_amount'], 2),
                'remaining_budget' => round($row['remaining_budget'], 2),
                'budget_utilization' => round($row['budget_utilization'], 4),
                'currency_id' => $row['currency_id'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function estimatedVsLoggedHours(Collection $snapshots): array
    {
        return $snapshots
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'estimated_hours' => round($row['budgeted_hours'], 2),
                'logged_hours' => round($row['logged_hours'], 2),
                'billable_hours' => round($row['billable_hours'], 2),
                'remaining_hours' => round($row['hours_remaining'], 2),
                'utilization' => round($row['utilization'], 4),
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function invoiceProgress(Collection $snapshots): array
    {
        return $snapshots
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'completion_percentage' => round($row['completion_percentage'], 2),
                'work_value' => round($row['labor_value'], 2),
                'invoiced_amount' => round($row['invoiced_amount'], 2),
                'paid_amount' => round($row['paid_amount'], 2),
                'outstanding_amount' => round($row['outstanding_amount'], 2),
                'unbilled_amount' => round($row['unbilled_value'], 2),
                'invoice_progress' => round($this->ratio($row['invoiced_amount'], $row['labor_value']), 4),
                'paid_progress' => round($this->ratio($row['paid_amount'], $row['invoiced_amount']), 4),
                'currency_id' => $row['currency_id'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function forecastCompletion(Collection $snapshots): array
    {
        return $snapshots
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'average_daily_velocity' => round($row['average_daily_velocity'], 2),
                'remaining_hours' => round($row['hours_remaining'], 2),
                'forecast_finish_date' => $row['forecast_finish_date'],
                'due_date' => $row['due_date'],
                'schedule_variance_days' => $row['schedule_variance_days'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function projectHealth(Collection $snapshots): array
    {
        return $snapshots
            ->sortBy('health_score')
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'health_score' => $row['health_score'],
                'health_status' => $row['health_status'],
                'indicators' => $row['health_indicators'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function profitability(Collection $snapshots): array
    {
        return $snapshots
            ->sortByDesc('profit_amount')
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'client_id' => $row['client_id'],
                'invoiced_amount' => round($row['invoiced_amount'], 2),
                'expense_amount' => round($row['expense_amount'], 2),
                'net_margin' => round($row['profit_amount'], 2),
                'margin_ratio' => round($row['margin_ratio'], 4),
                'currency_id' => $row['currency_id'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function teamContribution(Collection $snapshots): array
    {
        return $this->nestedSeries($snapshots, 'team_contribution', 'team');
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function timeDistribution(Collection $snapshots): array
    {
        return $this->nestedSeries($snapshots, 'time_distribution', 'tasks');
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function velocityTrend(Collection $snapshots): array
    {
        return $this->nestedSeries($snapshots, 'velocity_trend', 'velocity');
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function expenseBreakdown(Collection $snapshots): array
    {
        return $this->nestedSeries($snapshots, 'expense_breakdown', 'expense_categories');
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function cumulativeSpend(Collection $snapshots): array
    {
        return $this->nestedSeries($snapshots, 'cumulative_spend', 'cumulative_spend');
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function unbilledHours(Collection $snapshots): array
    {
        return $snapshots
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'unbilled_hours' => round($row['unbilled_hours'], 2),
                'unbilled_amount' => round($row['unbilled_value'], 2),
                'uninvoiced_tasks' => $row['uninvoiced_tasks'],
                'currency_id' => $row['currency_id'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function timelineVariance(Collection $snapshots): array
    {
        return $snapshots
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                'due_date' => $row['due_date'],
                'forecast_finish_date' => $row['forecast_finish_date'],
                'completion_percentage' => round($row['completion_percentage'], 2),
                'ideal_progress_percentage' => $row['ideal_progress_percentage'] !== null ? round($row['ideal_progress_percentage'], 2) : null,
                'schedule_variance_days' => $row['schedule_variance_days'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $snapshots
     * @return array<int, \stdClass>
     */
    private function nestedSeries(Collection $snapshots, string $seriesName, string $sourceKey): array
    {
        return $snapshots
            ->map(fn (array $row) => (object) [
                'project_id' => $row['project_id'],
                'project_name' => $row['project_name'],
                $seriesName => $row[$sourceKey],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTaskMetrics(): array
    {
        return [
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'invoiced_tasks' => 0,
            'uninvoiced_tasks' => 0,
            'running_tasks' => 0,
            'logged_hours' => 0.0,
            'billable_hours' => 0.0,
            'billable_value' => 0.0,
            'unbilled_hours' => 0.0,
            'unbilled_value' => 0.0,
            'team' => [],
            'tasks' => [],
            'velocity' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyInvoiceMetrics(): array
    {
        return [
            'invoice_count' => 0,
            'paid_invoice_count' => 0,
            'outstanding_invoice_count' => 0,
            'invoiced_amount' => 0.0,
            'paid_amount' => 0.0,
            'outstanding_amount' => 0.0,
            'project_invoice_count' => 0,
            'task_invoice_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyExpenseMetrics(): array
    {
        return [
            'expense_count' => 0,
            'expense_amount' => 0.0,
            'categories' => [],
            'daily' => [],
        ];
    }

    /**
     * @return array<int, array{date: string, hours: float, billable_hours: float, value: float}>
     */
    private function taskLogEntries(Task $task, Project $project): array
    {
        $entries = [];
        $logs = json_decode($task->time_log ?? '[]', true);

        if (is_array($logs)) {
            foreach ($logs as $log) {
                if (! is_array($log) || ! isset($log[0])) {
                    continue;
                }

                $startTimestamp = (int) $log[0];
                $endTimestamp = isset($log[1]) && (int) $log[1] > 0 ? (int) $log[1] : time();

                if ($startTimestamp <= 0 || $endTimestamp <= $startTimestamp) {
                    continue;
                }

                $hours = ($endTimestamp - $startTimestamp) / 3600;
                $billable = ! isset($log[3]) || (bool) $log[3];
                $rate = $this->taskRate($task, $project);
                $date = Carbon::createFromTimestamp($startTimestamp)
                    ->addSeconds($this->company->utc_offset())
                    ->format('Y-m-d');

                $entries[] = [
                    'date' => $date,
                    'hours' => $hours,
                    'billable_hours' => $billable ? $hours : 0.0,
                    'value' => $billable ? $hours * $rate : 0.0,
                ];
            }
        }

        if ($entries === [] && (int) $task->duration > 0) {
            $hours = (int) $task->duration / 3600;
            $date = $this->carbonDate($task->calculated_start_date ?? $task->created_at)?->format('Y-m-d') ?? now()->format('Y-m-d');

            $entries[] = [
                'date' => $date,
                'hours' => $hours,
                'billable_hours' => $hours,
                'value' => $hours * $this->taskRate($task, $project),
            ];
        }

        return $entries;
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

    private function budgetedAmount(Project $project, float $budgetedHours, float $taskRate): float
    {
        $budgetedAmount = (float) $project->budgeted_amount;

        return $budgetedAmount > 0 ? $budgetedAmount : $budgetedHours * $taskRate;
    }

    /**
     * @param array<string, mixed> $taskMetrics
     * @param array<string, mixed> $invoiceMetrics
     */
    private function unbilledValue(float $laborValue, float $invoicedAmount, array $taskMetrics, array $invoiceMetrics): float
    {
        if (
            (int) $invoiceMetrics['project_invoice_count'] === 0
            && (int) $invoiceMetrics['task_invoice_count'] > 0
            && (float) $taskMetrics['billable_value'] > 0
        ) {
            return max((float) $taskMetrics['unbilled_value'], 0.0);
        }

        return max($laborValue - $invoicedAmount, 0.0);
    }

    private function expenseAmount(Expense $expense): float
    {
        $amount = (float) $expense->foreign_amount > 0 ? (float) $expense->foreign_amount : (float) $expense->amount;

        if ($expense->uses_inclusive_taxes) {
            return $amount;
        }

        $taxFromRates = $amount * (
            (
                (float) ($expense->tax_rate1 ?? 0)
                + (float) ($expense->tax_rate2 ?? 0)
                + (float) ($expense->tax_rate3 ?? 0)
            ) / 100
        );

        return $amount
            + $taxFromRates
            + (float) ($expense->tax_amount1 ?? 0)
            + (float) ($expense->tax_amount2 ?? 0)
            + (float) ($expense->tax_amount3 ?? 0);
    }

    private function isCompletedTask(Task $task): bool
    {
        $statusOrder = $this->taskStatusOrder($task);

        return $statusOrder !== null && $statusOrder >= 4;
    }

    private function taskStatusOrder(Task $task): ?int
    {
        $statusOrder = $task->status->status_order ?? $task->status_order ?? null;

        return $statusOrder !== null ? (int) $statusOrder : null;
    }

    private function completionRatio(float $budgetedHours, float $loggedHours, int $totalTasks, int $completedTasks): float
    {
        if ($budgetedHours > 0) {
            return min($loggedHours / $budgetedHours, 1.0);
        }

        if ($totalTasks > 0) {
            return min($completedTasks / $totalTasks, 1.0);
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed> $taskMetrics
     */
    private function averageDailyVelocity(array $taskMetrics, Project $project, float $loggedHours): float
    {
        $velocity = $taskMetrics['velocity'];
        $activeDays = count(array_filter($velocity, fn (array $bucket) => (float) $bucket['hours'] > 0));
        $velocityHours = array_sum(array_map(fn (array $bucket) => (float) $bucket['hours'], $velocity));

        if ($activeDays > 0) {
            return $velocityHours / $activeDays;
        }

        if ($loggedHours <= 0) {
            return 0.0;
        }

        $start = $this->carbonDate($project->created_at) ?? now()->startOfDay();
        $elapsedDays = max((int) floor(($start->startOfDay()->diffInSeconds(now()->startOfDay()) / 86400)) + 1, 1);

        return $loggedHours / $elapsedDays;
    }

    private function forecastFinishDate(float $remainingHours, float $averageDailyVelocity): ?string
    {
        if ($remainingHours <= 0) {
            return now()->format('Y-m-d');
        }

        if ($averageDailyVelocity <= 0) {
            return null;
        }

        return now()
            ->startOfDay()
            ->addDays((int) ceil($remainingHours / $averageDailyVelocity))
            ->format('Y-m-d');
    }

    private function scheduleVarianceDays(?string $forecastDate, ?Carbon $dueDate): ?int
    {
        if (! $forecastDate || ! $dueDate) {
            return null;
        }

        $forecast = Carbon::parse($forecastDate)->startOfDay();
        $due = $dueDate->copy()->startOfDay();

        return (int) floor(($due->getTimestamp() - $forecast->getTimestamp()) / 86400);
    }

    private function idealProgressRatio(Carbon $projectStart, ?Carbon $dueDate): ?float
    {
        if (! $dueDate) {
            return null;
        }

        $start = $projectStart->copy()->startOfDay();
        $due = $dueDate->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($today->lte($start)) {
            return 0.0;
        }

        if ($today->gte($due)) {
            return 1.0;
        }

        $totalSeconds = max($due->getTimestamp() - $start->getTimestamp(), 1);
        $elapsedSeconds = max($today->getTimestamp() - $start->getTimestamp(), 0);

        return min($elapsedSeconds / $totalSeconds, 1.0);
    }

    /**
     * @return array{score: float, status: string, indicators: array<string, mixed>}
     */
    private function healthScore(
        float $budgetUtilization,
        ?int $scheduleVarianceDays,
        float $marginRatio,
        float $workValue,
        float $unbilledValue,
        float $invoicedAmount,
        float $outstandingAmount,
        float $completionRatio,
        ?Carbon $dueDate,
    ): array {
        $score = 100.0;
        $budgetOverrunRatio = max($budgetUtilization - 1, 0.0);
        $unbilledRatio = $this->ratio($unbilledValue, $workValue);
        $outstandingRatio = $this->ratio($outstandingAmount, $invoicedAmount);

        $score -= min($budgetOverrunRatio, 1.0) * 25;

        if ($scheduleVarianceDays !== null && $scheduleVarianceDays < 0) {
            $score -= min(abs($scheduleVarianceDays) / 30, 1.0) * 25;
        } elseif ($dueDate && $dueDate->lt(now()->startOfDay()) && $completionRatio < 1.0) {
            $score -= 20;
        }

        if ($marginRatio < 0) {
            $score -= 20;
        } elseif ($marginRatio > 0 && $marginRatio < 0.15) {
            $score -= 10;
        }

        if ($unbilledRatio > 0.2) {
            $score -= min($unbilledRatio, 1.0) * 10;
        }

        if ($outstandingRatio > 0.25) {
            $score -= min($outstandingRatio, 1.0) * 10;
        }

        $score = round(min(max($score, 0), 100), 2);

        return [
            'score' => $score,
            'status' => $this->healthStatus($score),
            'indicators' => [
                'budget_utilization' => round($budgetUtilization, 4),
                'schedule_variance_days' => $scheduleVarianceDays,
                'margin_ratio' => round($marginRatio, 4),
                'unbilled_ratio' => round($unbilledRatio, 4),
                'outstanding_ratio' => round($outstandingRatio, 4),
            ],
        ];
    }

    private function healthStatus(float $score): string
    {
        if ($score >= 75) {
            return 'green';
        }

        if ($score >= 50) {
            return 'amber';
        }

        return 'red';
    }

    /**
     * @param array<string, mixed> $taskMetrics
     * @param array<string, mixed> $expenseMetrics
     * @return array<int, array<string, mixed>>
     */
    private function buildCumulativeSpend(array $taskMetrics, array $expenseMetrics): array
    {
        $periods = collect(array_merge(
            array_keys($taskMetrics['velocity']),
            array_keys($expenseMetrics['daily']),
        ))
            ->unique()
            ->sort()
            ->values();

        $laborTotal = 0.0;
        $expenseTotal = 0.0;
        $rows = [];

        foreach ($periods as $period) {
            $labor = (float) ($taskMetrics['velocity'][$period]['task_value'] ?? 0);
            $expenses = (float) ($expenseMetrics['daily'][$period]['expense_amount'] ?? 0);
            $laborTotal += $labor;
            $expenseTotal += $expenses;

            $rows[] = [
                'period' => $period,
                'labor_value' => round($labor, 2),
                'expense_amount' => round($expenses, 2),
                'actual_amount' => round($labor + $expenses, 2),
                'cumulative_labor_value' => round($laborTotal, 2),
                'cumulative_expense_amount' => round($expenseTotal, 2),
                'cumulative_actual_amount' => round($laborTotal + $expenseTotal, 2),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function roundRows(array $rows): array
    {
        foreach ($rows as $rowKey => $row) {
            foreach ($row as $key => $value) {
                if (is_float($value)) {
                    $rows[$rowKey][$key] = round($value, 2);
                }
            }
        }

        return array_values($rows);
    }

    /**
     * @param array<string, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function seriesRows(array $rows): array
    {
        ksort($rows);

        return $this->roundRows(array_values($rows));
    }

    private function userName(Task $task): string
    {
        return $task->user->present()->name();
    }

    private function ratio(float $value, float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return $value / $total;
    }

    private function encodedPrimaryKey(int $id): string
    {
        return $this->encodePrimaryKey($id);
    }

    private function carbonDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        if (is_string($value) && ctype_digit($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
