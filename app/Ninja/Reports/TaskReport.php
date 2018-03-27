<?php

namespace App\Ninja\Reports;

use App\Models\Task;
use Utils;

class TaskReport extends AbstractReport
{
    public function getColumns()
    {
        return [
            'client' => [],
            'start_date' => [],
            'project' => [],
            'description' => [],
            'duration' => [],
            'amount' => [],
            'user' => ['columnSelector-false'],
        ];
    }

    public function run()
    {
        $startDate = date_create($this->startDate);
        $endDate = date_create($this->endDate);
        $subgroup = $this->options['subgroup'];

        $tasks = Task::scope()
                    ->orderBy('created_at', 'desc')
                    ->with('client.contacts', 'project', 'account', 'user')
                    ->withArchived()
                    ->dateRange($startDate, $endDate);

        foreach ($tasks->get() as $task) {
            $duration = $task->getDuration($startDate->format('U'), $endDate->modify('+1 day')->format('U'));
            $amount = $task->getRate() * ($duration / 60 / 60);
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
                Utils::formatTime($duration),
                Utils::formatMoney($amount, $currencyId),
                $task->user->getDisplayName(),
            ];

            $this->addToTotals($currencyId, 'duration', $duration);
            $this->addToTotals($currencyId, 'amount', $amount);

            if ($subgroup == 'project') {
                $dimension = $task->present()->project;
            } else {
                $dimension = $this->getDimension($task);
            }
            $this->addChartData($dimension, $task->created_at, round($duration / 60 / 60, 2));
        }
    }
}
