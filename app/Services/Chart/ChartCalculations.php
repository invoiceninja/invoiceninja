<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Chart;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Task;

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

        if(in_array($data['period'],['current,previous']))
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);

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

        if(in_array($data['period'],['current,previous']))
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);

        match ($data['calculation']) {
            'sum' => $result = $q->sum('balance'),
            'avg' => $result = $q->avg('balance'),
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

        if(in_array($data['period'],['current,previous']))
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);

        match ($data['calculation']) {
            'sum' => $result = $q->sum('amount'),
            'avg' => $result = $q->avg('amount'),
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

        if(in_array($data['period'],['current,previous']))
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);

        match ($data['calculation']) {
            'sum' => $result = $q->sum('refunded'),
            'avg' => $result = $q->avg('refunded'),
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
                    ->where(function ($qq){
                        $qq->where('due_date', '>=', now()->toDateString())->orWhereNull('due_date');
                    });

        if(in_array($data['period'],['current,previous']))
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);

        match ($data['calculation']) {
            'sum' => $result = $q->sum('refunded'),
            'avg' => $result = $q->avg('refunded'),
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
                    ->where(function ($qq){
                        $qq->where('due_date', '>=', now()->toDateString())->orWhereNull('due_date');
                    });

        if(in_array($data['period'],['current,previous']))
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);

        match ($data['calculation']) {
            'sum' => $result = $q->sum('refunded'),
            'avg' => $result = $q->avg('refunded'),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getLoggedTasks($data): int|float
    {
        //tasks with at least 1 timelog entry.

        $result = 0;
        $calculated = collect();

        $q = Task::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted',0);

        if(in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('calculated_start_date', [$data['start_date'], $data['end_date']]);
        }

        if($data['calculation'] != 'count' && $data['format'] == 'money')
        {
            if($data['currency_id'] != '999')
            {

                $q->whereHas('client', function ($query) use ($data){
                    $query->where('settings->currency_id', $data['currency_id']);
                });

            }

            $calculated = $this->taskMoneyCalculator($q, $data);

        }

        if($data['calculation'] != 'count' && $data['format'] == 'time')
        {
            $calculated = $q->get()->map(function ($t){
                return $t->calcDuration();
            });
        }

        match ($data['calculation']) {
            'sum' => $result = $calculated->sum(),
            'avg' => $result = $calculated->avg(),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    private function taskMoneyCalculator($query, $data)
    {

        return $query->get()
                    ->when($data['currency_id'] == '999', function ($collection) {
                        $collection->map(function ($t) {
                            return $t->taskCompanyValue();
                        });
                    })
                    ->when($data['currency_id'] != '999', function ($collection) {

                        $collection->map(function ($t) {
                            return $t->taskValue();
                        });

                    });

    }

    public function getInvoicedTasks($data): int|float
    {

        $result = 0;
        $calculated = collect();

        $q = Task::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereHas('invoice');

        if(in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('calculated_start_date', [$data['start_date'], $data['end_date']]);
        }

        if($data['calculation'] != 'count' && $data['format'] == 'money') {

            if($data['currency_id'] != '999') {

                $q->whereHas('client', function ($query) use ($data) {
                    $query->where('settings->currency_id', $data['currency_id']);
                });

            }

            $calculated = $this->taskMoneyCalculator($q, $data);

        }

        if($data['calculation'] != 'count' && $data['format'] == 'time') {
            $calculated = $q->get()->map(function ($t) {
                return $t->calcDuration();
            });
        }

        match ($data['calculation']) {
            'sum' => $result = $calculated->sum(),
            'avg' => $result = $calculated->avg(),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }
}