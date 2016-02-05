<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class TaskPresenter extends Presenter {

    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function times($account)
    {
        $parts = json_decode($this->entity->time_log) ?: [];
        $times = [];

        foreach ($parts as $part) {
            $start = $part[0];
            if (count($part) == 1 || !$part[1]) {
                $end = time();
            } else {
                $end = $part[1];
            }

            $start = $account->formatDateTime("@{$start}");
            $end = $account->formatTime("@{$end}");

            $times[] = "### {$start} - {$end}";
        }

        return implode("\n", $times);
    }

    public function status()
    {
        $class = $text = '';

        if ($this->entity->is_deleted) {
            $class = 'danger';
            $text = trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            $class = 'warning';
            $text = trans('texts.archived');
        } else {
            $class = 'success';
            $text = trans('texts.active');
        }

        return "<span class=\"label label-{$class}\">{$text}</span>";
    }

}