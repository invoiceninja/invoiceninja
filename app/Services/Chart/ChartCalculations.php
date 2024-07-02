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
}