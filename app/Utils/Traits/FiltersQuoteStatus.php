<?php

namespace App\Utils\Traits;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;

trait FiltersQuoteStatus
{
    protected function filterQuoteStatus(Builder $query, string $status, string $today): Builder
    {
        $statuses = explode(',', $status);

        if ($status === '' || in_array('all', $statuses, true)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($statuses, $today) {
            $statusIds = array_intersect_key([
                'draft' => Quote::STATUS_DRAFT,
                'approved' => Quote::STATUS_APPROVED,
                'cancelled' => Quote::STATUS_CANCELLED,
                'rejected' => Quote::STATUS_REJECTED,
            ], array_flip($statuses));

            if ($statusIds !== []) {
                $query->orWhereIn('status_id', array_values($statusIds));
            }

            if (in_array('sent', $statuses, true)) {
                $query->orWhere(function (Builder $query) use ($today) {
                    $query->where('status_id', Quote::STATUS_SENT)
                        ->where(function (Builder $query) use ($today) {
                            $query->whereNull('due_date')->orWhere('due_date', '>=', $today);
                        });
                });
            }

            if (in_array('expired', $statuses, true)) {
                $query->orWhere(function (Builder $query) use ($today) {
                    $query->where('status_id', Quote::STATUS_SENT)->where('due_date', '<', $today);
                });
            }

            if (in_array('upcoming', $statuses, true)) {
                $query->orWhere(function (Builder $query) use ($today) {
                    $query->where('status_id', Quote::STATUS_SENT)
                        ->where('due_date', '>=', $today);
                });
            }

            if (in_array('converted', $statuses, true)) {
                $query->orWhereNotNull('invoice_id');
            }
        });
    }
}
