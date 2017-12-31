<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class ProjectDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PROJECT;
    public $sortCol = 1;

    public function columns()
    {
        return [
            [
                'project',
                function ($model) {
                    if (! Auth::user()->can('editByOwner', [ENTITY_PROJECT, $model->user_id])) {
                        return $model->project;
                    }

                    $str = link_to("projects/{$model->public_id}", $model->project)->toHtml();
                    return $this->addNote($str, $model->private_notes);
                },
            ],
            [
                'client_name',
                function ($model) {
                    if ($model->client_public_id) {
                        if (! Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])) {
                            return Utils::getClientDisplayName($model);
                        }

                        return link_to("clients/{$model->client_public_id}", $model->client_name)->toHtml();
                    } else {
                        return '';
                    }
                },
            ],
            [
                'due_date',
                function ($model) {
                    return Utils::fromSqlDate($model->due_date);
                },
            ],
            [
                'budgeted_hours',
                function ($model) {
                    return $model->budgeted_hours ?: '';
                },
            ],
            [
                'task_rate',
                function ($model) {
                    return floatval($model->task_rate) ? Utils::formatMoney($model->task_rate) : '';
                }
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_project'),
                function ($model) {
                    return URL::to("projects/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PROJECT, $model->user_id]);
                },
            ],
            [
                trans('texts.invoice_project'),
                function ($model) {
                    return "javascript:submitForm_project('invoice', {$model->public_id})";
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_INVOICE);
                },
            ],
        ];
    }
}
