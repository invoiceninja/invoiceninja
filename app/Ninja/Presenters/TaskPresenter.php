<?php

namespace App\Ninja\Presenters;

use Utils;

/**
 * Class TaskPresenter.
 */
class TaskPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function description()
    {
        return substr($this->entity->description, 0, 40) . (strlen($this->entity->description) > 40 ? '...' : '');
    }

    public function project()
    {
        return $this->entity->project ? $this->entity->project->name : '';
    }

    /**
     * @param $account
     * @param mixed $showProject
     *
     * @return mixed
     */
    public function invoiceDescription($account, $showProject)
    {
        $str = '';

        if ($showProject && $project = $this->project()) {
            $str .= "## {$project}\n\n";
        }

        if ($description = trim($this->entity->description)) {
            $str .= $description . "\n\n";
        }

        $parts = json_decode($this->entity->time_log) ?: [];
        $times = [];

        foreach ($parts as $part) {
            $start = $part[0];
            if (count($part) == 1 || ! $part[1]) {
                $end = time();
            } else {
                $end = $part[1];
            }

            $start = $account->formatDateTime('@' . intval($start));
            $end = $account->formatTime('@' . intval($end));

            $times[] = "### {$start} - {$end}";
        }

        return $str . implode("\n", $times);
    }

    public function calendarEvent($subColors = false)
    {
        $data = parent::calendarEvent();
        $task = $this->entity;
        $account = $task->account;
        $date = $account->getDateTime();

        $data->title = trans('texts.task');
        if ($project = $this->project()) {
            $data->title .= ' | ' . $project;
        }
        if ($description = $this->description()) {
            $data->title .= ' | ' . $description;
        }
        $data->allDay = false;

        if ($subColors && $task->project_id) {
            $data->borderColor = $data->backgroundColor = Utils::brewerColor($task->project->public_id);
        } else {
            $data->borderColor = $data->backgroundColor = '#a87821';
        }

        $parts = json_decode($task->time_log) ?: [];
        if (count($parts)) {
            $first = $parts[0];
            $start = $first[0];
            $date->setTimestamp($start);
            $data->start = $date->format('Y-m-d H:i:m');

            $last = $parts[count($parts) - 1];
            $end = count($last) == 2 ? $last[1] : $last[0];
            $date->setTimestamp($end);
            $data->end = $date->format('Y-m-d H:i:m');
        }

        return $data;
    }
}
