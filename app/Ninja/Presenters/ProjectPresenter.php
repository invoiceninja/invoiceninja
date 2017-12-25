<?php

namespace App\Ninja\Presenters;

use Utils;

class ProjectPresenter extends EntityPresenter
{
    public function calendarEvent($subColors = false)
    {
        $data = parent::calendarEvent();
        $project = $this->entity;

        $data->title = trans('texts.project') . ': ' . $project->name;
        $data->start = $project->due_date;

        if ($subColors) {
            $data->borderColor = $data->backgroundColor = Utils::brewerColor($project->public_id);
        } else {
            $data->borderColor = $data->backgroundColor = '#676767';
        }

        return $data;
    }

    /**
     * @return string
     */
    public function taskRate()
    {
      if (floatval($this->entity->task_rate)) {
          return Utils::roundSignificant($this->entity->task_rate);
      } else {
          return '';
      }
    }

    /**
     * @return string
     */
    public function defaultTaskRate()
    {
      if ($rate = $this->taskRate()) {
          return $rate;
      } else {
          return $this->entity->client->present()->defaultTaskRate;
      }
    }

}
