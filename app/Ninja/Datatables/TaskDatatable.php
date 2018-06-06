<?php

namespace App\Ninja\Datatables;

use App\Models\Task;
use App\Models\TaskStatus;
use Auth;
use URL;
use Utils;

class TaskDatatable extends EntityDatatable
{
    public $entityType = ENTITY_TASK;
    public $sortCol = 3;

    public function columns()
    {
        return [
            [
                'client_name',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_CLIENT, $model]))
                        return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml() : '';
                    else
                        return Utils::getClientDisplayName($model);

                },
                ! $this->hideClient,
            ],
            [
                'project',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_PROJECT, $model]))
                        return $model->project_public_id ? link_to("projects/{$model->project_public_id}", $model->project)->toHtml() : '';
                    else
                        return $model->project;

                },
            ],
            [
                'date',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_EXPENSE, $model]))
                        return link_to("tasks/{$model->public_id}/edit", Task::calcStartTime($model))->toHtml();
                    else
                        return Task::calcStartTime($model);

                },
            ],
            [
                'duration',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_EXPENSE, $model]))
                        return link_to("tasks/{$model->public_id}/edit", Utils::formatTime(Task::calcDuration($model)))->toHtml();
                    else
                        return Utils::formatTime(Task::calcDuration($model));

                },
            ],
            [
                'description',
                function ($model) {
                    return $this->showWithTooltip($model->description);
                },
            ],
            [
                'status',
                function ($model) {
                    return self::getStatusLabel($model);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_task'),
                function ($model) {
                    return URL::to('tasks/'.$model->public_id.'/edit');
                },
                function ($model) {
                    return (! $model->deleted_at || $model->deleted_at == '0000-00-00') && Auth::user()->can('view', [ENTITY_TASK, $model]);
                },
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("/invoices/{$model->invoice_public_id}/edit");
                },
                function ($model) {
                    return $model->invoice_number && Auth::user()->can('view', [ENTITY_TASK, $model]);
                },
            ],
            [
                trans('texts.resume_task'),
                function ($model) {
                    return "javascript:submitForm_task('resume', {$model->public_id})";
                },
                function ($model) {
                    return ! $model->is_running && Auth::user()->can('edit', [ENTITY_TASK, $model]);
                },
            ],
            [
                trans('texts.stop_task'),
                function ($model) {
                    return "javascript:submitForm_task('stop', {$model->public_id})";
                },
                function ($model) {
                    return $model->is_running && Auth::user()->can('edit', [ENTITY_TASK, $model]);
                },
            ],
            [
                trans('texts.invoice_task'),
                function ($model) {
                    return "javascript:submitForm_task('invoice', {$model->public_id})";
                },
                function ($model) {
                    return ! $model->is_running && ! $model->invoice_number && (! $model->deleted_at || $model->deleted_at == '0000-00-00') && Auth::user()->canCreateOrEdit(ENTITY_INVOICE);
                },
            ],
        ];
    }

    private function getStatusLabel($model)
    {
        $label = Task::calcStatusLabel($model->is_running, $model->balance, $model->invoice_number, $model->task_status);
        $class = Task::calcStatusClass($model->is_running, $model->balance, $model->invoice_number);

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }

    public function bulkActions()
    {
        $actions = [];

        $statuses = TaskStatus::scope()->orderBy('sort_order')->get();

        foreach ($statuses as $status) {
            $actions[] = [
                'label' => sprintf('%s %s', trans('texts.mark'), $status->name),
                'url' => 'javascript:submitForm_' . $this->entityType . '("update_status:' . $status->public_id . '")',
            ];
        }

        if (count($actions)) {
            $actions[] = \DropdownButton::DIVIDER;
        }

        $actions = array_merge($actions, parent::bulkActions());

        return $actions;
    }
}
