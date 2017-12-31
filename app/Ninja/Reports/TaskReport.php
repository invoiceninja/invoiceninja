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
        'amount',
    ];

    public function run()
    {
        $startDate = date_create($this->startDate);
        $endDate = date_create($this->endDate);

        $tasks = Task::scope()
                    ->orderBy('created_at', 'desc')
                    ->with('client.contacts', 'project', 'account')
                    ->withArchived()
                    ->dateRange($startDate, $endDate);

        foreach ($tasks->get() as $task) {
            $amount = $task->getRate() * ($task->getDuration() / 60 / 60);
            if ($task->client && $task->client->currency_id) {
                $currencyId = $task->client->currency_id;
            } else {
                $currencyId = auth()->user()->account->getCurrencyId();
            }

            $this->data[] = [
                $task->client ? ($this->isExport ? $task->client->getDisplayName() : $task->client->present()->link) : trans('texts.unassigned'),
                $this->isExport ? $task->getStartTime() : link_to($task->present()->url, $task->getStartTime()),
                $task->present()->project,
                $task->description,
                Utils::formatTime($task->getDuration()),
                Utils::formatMoney($amount, $currencyId),
            ];

            $this->addToTotals($currencyId, 'duration', $task->getDuration());
            $this->addToTotals($currencyId, 'amount', $amount);
        }
    }
}
