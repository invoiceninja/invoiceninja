<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Task;

class GenerateCalendarEvents extends Job
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $events = [];
        $filter = request()->filter ?: [];

        $data = [
            ENTITY_INVOICE => Invoice::scope()->invoices(),
            ENTITY_QUOTE => Invoice::scope()->quotes(),
            ENTITY_TASK => Task::scope(),
            ENTITY_PAYMENT => Payment::scope(),
            ENTITY_EXPENSE => Expense::scope(),
        ];

        foreach ($data as $type => $source) {
            if (! count($filter) || in_array($type, $filter)) {
                foreach ($source->get() as $entity) {
                    $events[] = $entity->present()->calendarEvent;
                }
            }
        }

        return $events;
    }
}
