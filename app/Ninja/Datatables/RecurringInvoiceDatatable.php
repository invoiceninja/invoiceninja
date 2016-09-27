<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;

class RecurringInvoiceDatatable extends EntityDatatable
{
    public $entityType = ENTITY_RECURRING_INVOICE;

    public function columns()
    {
        return [
            [
                'frequency',
                function ($model) {
                    $frequency = strtolower($model->frequency);
                    $frequency = preg_replace('/\s/', '_', $frequency);
                    return link_to("invoices/{$model->public_id}", trans('texts.freq_'.$frequency))->toHtml();
                }
            ],
            [
                'client_name',
                function ($model) {
                    return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                },
                ! $this->hideClient
            ],
            [
                'start_date',
                function ($model) {
                    return Utils::fromSqlDate($model->start_date);
                }
            ],
            [
                'end_date',
                function ($model) {
                    return Utils::fromSqlDate($model->end_date);
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                }
            ]
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_invoice'),
                function ($model) {
                    return URL::to("invoices/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans("texts.clone_invoice"),
                function ($model) {
                    return URL::to("invoices/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_INVOICE);
                }
            ],

        ];
    }

}
