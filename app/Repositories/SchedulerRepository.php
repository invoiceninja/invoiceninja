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

namespace App\Repositories;

use App\Models\Scheduler;
use App\Services\Scheduler\PaymentSchedule;

class SchedulerRepository extends BaseRepository
{
    /**
     * Saves the scheduler.
     *
     * @param      array                     $data     The data
     * @param      \App\Models\Scheduler     $scheduler  The scheduler
     *
     * @return     \App\Models\Scheduler
     */
    public function save(array $data, Scheduler $scheduler): Scheduler
    {

        $scheduler->fill($data);

        $scheduler->save();

        $scheduler->adjustOffset();

        // Populate the invoice partial / due dates when a payment schedule is first created.
        // A single-instalment schedule force-deletes itself during seeding, so fall back to
        // the in-memory instance when there is no longer a persisted record to refresh.
        if ($scheduler->wasRecentlyCreated && $scheduler->template === 'payment_schedule') {
            (new PaymentSchedule($scheduler))->seed();
        }

        return $scheduler->fresh() ?? $scheduler;
    }
}
