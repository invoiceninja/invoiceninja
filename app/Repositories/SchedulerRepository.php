<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Repositories;

use App\Models\Scheduler;

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

        return $scheduler->fresh();
    }
}
