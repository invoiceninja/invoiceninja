<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;
use App\Models\Task;

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
                    if(!Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])){
                        return Utils::getClientDisplayName($model);
                    }

                    return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml() : '';
                },
                ! $this->hideClient
            ],
            [
                'project',
                function ($model) {
                    if(!Auth::user()->can('editByOwner', [ENTITY_PROJECT, $model->project_user_id])){
                        return $model->project;
                    }

                    return $model->project_public_id ? link_to("projects/{$model->project_public_id}/edit", $model->project)->toHtml() : '';
                }
            ],
            [
                'date',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_EXPENSE, $model->user_id])){
                        return Task::calcStartTime($model);
                    }
                    return link_to("tasks/{$model->public_id}/edit", Task::calcStartTime($model))->toHtml();
                }
            ],
            [
                'duration',
                function($model) {
                    return Utils::formatTime(Task::calcDuration($model));
                }
            ],
            [
                'description',
                function ($model) {
                    return $model->description;
                }
            ],
            [
                'status',
                function ($model) {
                    return self::getStatusLabel($model);
                }
            ]
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
                    return (!$model->deleted_at || $model->deleted_at == '0000-00-00') && Auth::user()->can('editByOwner', [ENTITY_TASK, $model->user_id]);
                }
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("/invoices/{$model->invoice_public_id}/edit");
                },
                function ($model) {
                    return $model->invoice_number && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->invoice_user_id]);
                }
            ],
            [
                trans('texts.stop_task'),
                function ($model) {
                    return "javascript:submitForm_task('stop', {$model->public_id})";
                },
                function ($model) {
                    return $model->is_running && Auth::user()->can('editByOwner', [ENTITY_TASK, $model->user_id]);
                }
            ],
            [
                trans('texts.invoice_task'),
                function ($model) {
                    return "javascript:submitForm_task('invoice', {$model->public_id})";
                },
                function ($model) {
                    return ! $model->invoice_number && (!$model->deleted_at || $model->deleted_at == '0000-00-00') && Auth::user()->can('create', ENTITY_INVOICE);
                }
            ]
        ];
    }

    private function getStatusLabel($model)
    {
        if ($model->invoice_number) {
            if (floatval($model->balance)) {
                $label = trans('texts.invoiced');
                $class = 'default';
            } else {
                $class = 'success';
                $label = trans('texts.paid');
            }
        } elseif ($model->is_running) {
            $class = 'primary';
            $label = trans('texts.running');
        } else {
            $class = 'warning';
            $label = trans('texts.logged');
        }

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }


}
