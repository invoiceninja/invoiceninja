<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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
