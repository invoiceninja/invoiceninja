<?php

namespace App\Ninja\Presenters;

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

    public function calendarEvent()
    {
        $data = parent::calendarEvent();
        $task = $this->entity;

        $data->title = trans('texts.task');
        if ($project = $this->project()) {
            $data->title .= ' | ' . $project;
        }
        $data->title .= ' | ' . $this->description();

        $data->allDay = false;
        $data->borderColor = $data->backgroundColor = 'purple';

        $parts = json_decode($task->time_log) ?: [];
        if (count($parts)) {
            $first = $parts[0];
            $start = $first[0];
            $data->start = date('Y-m-d H:i:m', $start);

            $last = $parts[count($parts) - 1];
            $end = count($last) == 2 ? $last[1] : $last[0];
            $data->end = date('Y-m-d H:i:m', $end);
        }

        return $data;
    }
}
