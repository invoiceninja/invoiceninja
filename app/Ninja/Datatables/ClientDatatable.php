<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class ClientDatatable extends EntityDatatable
{
    public $entityType = ENTITY_CLIENT;
    public $sortCol = 4;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("clients/{$model->public_id}", $model->name ?: '')->toHtml();
                },
            ],
            [
                'contact',
                function ($model) {
                    return link_to("clients/{$model->public_id}", $model->contact ?: '')->toHtml();
                },
            ],
            [
                'email',
                function ($model) {
                    return link_to("clients/{$model->public_id}", $model->email ?: '')->toHtml();
                },
            ],
            [
                'id_number',
                function ($model) {
                    return $model->id_number;
                },
                Auth::user()->account->clientNumbersEnabled()
            ],
            [
                'client_created_at',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->created_at));
                },
            ],
            [
                'last_login',
                function ($model) {
                    return Utils::timestampToDateString(strtotime($model->last_login));
                },
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_client'),
                function ($model) {
                    return URL::to("clients/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_CLIENT, $model->user_id]);
                },
            ],
            [
                '--divider--', function () {
                    return false;
                },
                function ($model) {
                    $user = Auth::user();

                    return $user->can('editByOwner', [ENTITY_CLIENT, $model->user_id]) && ($user->can('create', ENTITY_TASK) || $user->can('create', ENTITY_INVOICE));
                },
            ],
            [
                trans('texts.new_task'),
                function ($model) {
                    return URL::to("tasks/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_TASK);
                },
            ],
            [
                trans('texts.new_invoice'),
                function ($model) {
                    return URL::to("invoices/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_INVOICE);
                },
            ],
            [
                trans('texts.new_quote'),
                function ($model) {
                    return URL::to("quotes/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->hasFeature(FEATURE_QUOTES) && Auth::user()->can('create', ENTITY_QUOTE);
                },
            ],
            [
                '--divider--', function () {
                    return false;
                },
                function ($model) {
                    $user = Auth::user();

                    return ($user->can('create', ENTITY_TASK) || $user->can('create', ENTITY_INVOICE)) && ($user->can('create', ENTITY_PAYMENT) || $user->can('create', ENTITY_CREDIT) || $user->can('create', ENTITY_EXPENSE));
                },
            ],
            [
                trans('texts.enter_payment'),
                function ($model) {
                    return URL::to("payments/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_PAYMENT);
                },
            ],
            [
                trans('texts.enter_credit'),
                function ($model) {
                    return URL::to("credits/create/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_CREDIT);
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
