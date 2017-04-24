<?php

namespace App\Ninja\Reports;

use App\Models\Task;
use Utils;

class TaskReport extends AbstractReport
{
    public $columns = [
        'client',
        'date',
        'project',
        'description',
        'duration',
    ];

    public function run()
    {
        $tasks = Task::scope()
                    ->orderBy('created_at', 'desc')
                    ->with('client.contacts')
                    ->withArchived()
                    ->dateRange($this->startDate, $this->endDate);

        foreach ($tasks->get() as $task) {
            $this->data[] = [
                $task->client ? ($this->isExport ? $task->client->getDisplayName() : $task->client->present()->link) : trans('texts.unassigned'),
                link_to($task->present()->url, $task->getStartTime()),
                $task->present()->project,
                $task->present()->description,
                Utils::formatTime($task->getDuration()),
            ];
        }
    }
}
