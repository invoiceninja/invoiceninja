<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class VendorDatatable extends EntityDatatable
{
    public $entityType = ENTITY_VENDOR;
    public $sortCol = 4;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    $str = link_to("vendors/{$model->public_id}", $model->name ?: '')->toHtml();
                    return $this->addNote($str, $model->private_notes);
                },
            ],
            [
                'city',
                function ($model) {
                    return $model->city;
                },
            ],
            [
                'work_phone',
                function ($model) {
                    return $model->work_phone;
                },
            ],
            [
                'email',
                function ($model) {
                    return link_to("vendors/{$model->public_id}", $model->email ?: '')->toHtml();
                },
            ],
            [
                'created_at',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->created_at));
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_vendor'),
                function ($model) {
                    return URL::to("vendors/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('view', [ENTITY_VENDOR, $model]);
                },
            ],
            [
                '--divider--', function () {
                    return false;
                },
                function ($model) {
                    return Auth::user()->can('edit', [ENTITY_VENDOR, $model]) && Auth::user()->can('create', ENTITY_EXPENSE);
                },

            ],
            [
                trans('texts.enter_expense'),
                function ($model) {
                    return URL::to("expenses/create/0/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_EXPENSE);
                },
            ],
        ];
    }
}
