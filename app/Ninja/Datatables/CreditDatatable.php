<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class CreditDatatable extends EntityDatatable
{
    public $entityType = ENTITY_CREDIT;
    public $sortCol = 4;

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
                'amount',
                function ($model) {
                    if(Auth::user()->can('view', [ENTITY_CLIENT, $model]))
                        return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id) . '<span '.Utils::getEntityRowClass($model).'/>';
                },
            ],
            [
                'balance',
                function ($model) {
                    if(Auth::user()->can('view', [ENTITY_CLIENT, $model]))
                        return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                },
            ],
            [
                'credit_date',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_CREDIT, $model]))
                        return link_to("credits/{$model->public_id}/edit", Utils::fromSqlDate($model->credit_date_sql))->toHtml();
                    else
                        return Utils::fromSqlDate($model->credit_date_sql);

                },
            ],
            [
                'public_notes',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_CREDIT, $model]))
                        return e($model->public_notes);
                },
            ],
            [
                'private_notes',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_CREDIT, $model]))
                        return e($model->private_notes);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_credit'),
                function ($model) {
                    return URL::to("credits/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('view', [ENTITY_CREDIT, $model]);
                },
            ],
            [
                trans('texts.apply_credit'),
                function ($model) {
                    return URL::to("payments/create/{$model->client_public_id}") . '?paymentTypeId=1';
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_PAYMENT);
                },
            ],
        ];
    }
}
