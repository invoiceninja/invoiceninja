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
                    if (Auth::user()->can('view', [ENTITY_PROJECT, $model]))
                        return $this->addNote(link_to("projects/{$model->public_id}", $model->project)->toHtml(), $model->private_notes);
                    else
                        return $model->project;


                },
            ],
            [
                'client_name',
                function ($model) {
                    if ($model->client_public_id) {
                        if (Auth::user()->can('view', [ENTITY_CLIENT, $model]))
                            return link_to("clients/{$model->client_public_id}", $model->client_name)->toHtml();
                        else
                            return Utils::getClientDisplayName($model);

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
                    return Auth::user()->can('view', [ENTITY_PROJECT, $model]);
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
