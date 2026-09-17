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

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Task;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Class ChartCalculations.
 */
trait ChartCalculations
{
    public function getActiveInvoices($data): int|float
    {
        $result = 0;

        $q = Invoice::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [2,3,4]);

        if (in_array($data['period'], ['current','previous']) && ($data['date_range'] ?? null) !== 'all_time') {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('amount'),
            'avg' => $result = $q->avg('amount'),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getOutstandingInvoices($data): int|float
    {
        $result = 0;

        $q = Invoice::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [2,3]);

        if (in_array($data['period'], ['current','previous']) && ($data['date_range'] ?? null) !== 'all_time') {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('balance') ?? 0,
            'avg' => $result = $q->avg('balance') ?? 0,
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getCompletedPayments($data): int|float
    {
        $result = 0;

        $q = Payment::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->where('status_id', 4);

        if (in_array($data['period'], ['current','previous']) && ($data['date_range'] ?? null) !== 'all_time') {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('amount') ?? 0,
            'avg' => $result = $q->avg('amount') ?? 0,
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getRefundedPayments($data): int|float
    {
        $result = 0;

        $q = Payment::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [5,6]);

        if (in_array($data['period'], ['current','previous']) && ($data['date_range'] ?? null) !== 'all_time') {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('refunded') ?? 0,
            'avg' => $result = $q->avg('refunded') ?? 0,
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getActiveQuotes($data): int|float
    {
        $result = 0;

        $q = Quote::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [2,3])
                    ->where(function ($qq) {
                        $qq->where('due_date', '>=', now()->toDateString())->orWhereNull('due_date');
                    });

        if (in_array($data['period'], ['current','previous']) && ($data['date_range'] ?? null) !== 'all_time') {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('amount') ?? 0,
            'avg' => $result = $q->avg('amount') ?? 0,
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getUnapprovedQuotes($data): int|float
    {
        $result = 0;

        $q = Quote::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [2])
                    ->where(function ($qq) {
                        $qq->where('due_date', '>=', now()->toDateString())->orWhereNull('due_date');
                    });

        if (in_array($data['period'], ['current','previous']) && ($data['date_range'] ?? null) !== 'all_time') {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('amount') ?? 0,
            'avg' => $result = $q->avg('amount') ?? 0,
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getLoggedTasks($data): int|float
    {

        $q = $this->taskQuery($data);

        return $this->taskCalculations($q, $data);

    }

    public function getPaidTasks($data): int|float
    {
        $q = $this->taskQuery($data);
        $q->whereHas('invoice', function ($query) {
            $query->where('status_id', 4)->where('is_deleted', 0);
        });

        return $this->taskCalculations($q, $data);

    }

    public function getInvoicedTasks($data): int|float
    {

        $q = $this->taskQuery($data);
        $q->whereHas('invoice');

        return $this->taskCalculations($q, $data);

    }

    public function getTaskEstimatedDuration(array $data): int|float
    {
        $query = $this->taskQuery($data)->whereNotNull('estimated_duration');

        return match ($data['calculation']) {
            'sum' => $query->sum('estimated_duration') ?? 0,
            'avg' => $query->avg('estimated_duration') ?? 0,
            default => 0,
        };
    }

    public function getTaskRemainingEstimatedDuration(array $data): int|float
    {
        $durations = $this->activeTasks($this->taskQuery($data))
            ->whereNotNull('estimated_duration')
            ->map(fn (Task $task): int => max((int) $task->estimated_duration - (int) $task->calcDuration(), 0));

        return match ($data['calculation']) {
            'sum' => $durations->sum() ?? 0,
            'avg' => $durations->avg() ?? 0,
            default => 0,
        };
    }

    public function getUnestimatedTasks(array $data): int
    {
        return $this->activeTasks($this->taskQuery($data))
            ->whereNull('estimated_duration')
            ->count();
    }

    public function getTasksOverEstimate(array $data): int
    {
        return $this->activeTasks($this->taskQuery($data))
            ->whereNotNull('estimated_duration')
            ->filter(fn (Task $task): bool => $task->calcDuration() > (int) $task->estimated_duration)
            ->count();
    }

    public function getOverdueTasks(array $data): int
    {
        $timezone = $this->company->timezone()?->name ?: config('app.timezone');
        $today = now($timezone)->toDateString();
        $query = $this->taskQuery($data)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today);

        return $this->activeTasks($query)->count();
    }

    public function getTasksDue(array $data): int
    {
        return $this->activeTasks($this->dueTaskQuery($data))->count();
    }

    /**
     * All Expenses
     */
    public function getLoggedExpenses($data): int|float
    {
        $q = $this->expenseQuery($data);

        return $this->expenseCalculations($q, $data);
    }


    /**
     * Expenses that should be invoiced - but are not yet invoiced.
     */
    public function getPendingExpenses($data): int|float
    {

        $q = $this->expenseQuery($data);
        $q->where('should_be_invoiced', true)->whereNull('invoice_id');
        return $this->expenseCalculations($q, $data);
    }

    /**
     * Invoiced.
     */
    public function getInvoicedExpenses($data): int|float
    {

        $q = $this->expenseQuery($data);
        $q->whereNotNull('invoice_id');
        return $this->expenseCalculations($q, $data);
    }

    /**
     * Paid.
     */
    public function getPaidExpenses($data): int|float
    {

        $q = $this->expenseQuery($data);
        $q->whereNotNull('payment_date');
        return $this->expenseCalculations($q, $data);
    }

    /**
     * Paid.
     */
    public function getInvoicedPaidExpenses($data): int|float
    {

        $q = $this->expenseQuery($data);
        $q->whereNotNull('invoice_id')->whereNotNull('payment_date');
        return $this->expenseCalculations($q, $data);
    }

    private function expenseCalculations(Builder $query, array $data): int|float
    {

        $result = 0;
        $calculated = $this->expenseCalculator($query, $data);

        match ($data['calculation']) {
            'sum' => $result = $calculated->sum() ?? 0,
            'avg' => $result = $calculated->avg() ?? 0,
            'count' => $result = $query->count() ?? 0,
            default => $result = 0,
        };

        return $result;


    }

    private function expenseCalculator(Builder $query, array $data)
    {

        return $query->get()
                    ->when($data['currency_id'] == '999', function ($collection) {
                        return $collection->map(function ($e) {
                            /** @var \App\Models\Expense $e */
                            return $e->amount * $e->exchange_rate;
                        });
                    })
                    ->when($data['currency_id'] != '999', function ($collection) {

                        return $collection->map(function ($e) {

                            /** @var \App\Models\Expense $e */
                            return $e->amount;
                        });

                    });

    }

    private function expenseQuery($data): Builder
    {
        $query = Expense::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        if (in_array($data['period'], ['current','previous']) && ($data['date_range'] ?? null) !== 'all_time') {
            $query->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        return $query;
    }

    ////////////////////////////////////////////////////////////////
    private function taskMoneyCalculator($query, $data)
    {

        return $query->get()
                    ->when($data['currency_id'] == '999', function ($collection) {
                        return $collection->map(function ($t) {
                            return $t->taskCompanyValue();
                        });
                    })
                    ->when($data['currency_id'] != '999', function ($collection) {
                        return $collection->map(function ($t) {
                            return $t->taskValue();
                        });
                    });

    }

    private function baseTaskQuery(): Builder
    {
        return Task::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0);
    }

    private function taskQuery(array $data): Builder
    {
        $q = $this->baseTaskQuery();

        if (in_array($data['period'], ['current','previous']) && ($data['date_range'] ?? null) !== 'all_time') {
            $q->whereBetween('calculated_start_date', [$data['start_date'], $data['end_date']]);
        }

        return $q;

    }

    private function dueTaskQuery(array $data): Builder
    {
        $query = $this->baseTaskQuery()->whereNotNull('due_date');

        if (in_array($data['period'], ['current', 'previous'], true) && ($data['date_range'] ?? null) !== 'all_time') {
            $query->whereBetween('due_date', [$data['start_date'], $data['end_date']]);
        }

        return $query;
    }

    /**
     * @return Collection<int, Task>
     */
    private function activeTasks(Builder $query): Collection
    {
        return $query
            ->with('status')
            ->get()
            ->filter(function (Task $task): bool {
                $statusOrder = $task->status->status_order ?? $task->status_order ?? null;

                return $statusOrder === null || (int) $statusOrder < 4;
            })
            ->values();
    }

    private function taskCalculations(Builder $q, array $data): int|float
    {

        $result = 0;
        $calculated = collect();

        if ($data['calculation'] != 'count' && $data['format'] == 'money') {
            if ($data['currency_id'] != '999') {

                $q->whereHas('client', function ($query) use ($data) {
                    $query->where('settings->currency_id', $data['currency_id']);
                });

            }

            $calculated = $this->taskMoneyCalculator($q, $data);

        }

        if ($data['calculation'] != 'count' && $data['format'] == 'time') {
            $calculated = $q->get()->map(function ($t) {
                return $t->calcDuration();
            });
        }

        match ($data['calculation']) {
            'sum' => $result = $calculated->sum() ?? 0,
            'avg' => $result = $calculated->avg() ?? 0,
            'count' => $result = $q->count() ?? 0,
            default => $result = 0,
        };

        return $result;

    }

}
