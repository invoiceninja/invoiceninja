<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;
use App\Models\Invoice;

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

                    return link_to("recurring_invoices/{$model->public_id}/edit", trans('texts.freq_'.$frequency))->toHtml();
                },
            ],
            [
                'client_name',
                function ($model) {
                    return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                },
                ! $this->hideClient,
            ],
            [
                'start_date',
                function ($model) {
                    return Utils::fromSqlDate($model->start_date_sql);
                },
            ],
            [
                'last_sent',
                function ($model) {
                    return Utils::fromSqlDate($model->last_sent_date_sql);
                },
            ],
            /*
            [
                'end_date',
                function ($model) {
                    return Utils::fromSqlDate($model->end_date_sql);
                },
            ],
            */
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                },
            ],
            [
                'private_notes',
                function ($model) {
                    return $model->private_notes;
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

    private function getStatusLabel($model)
    {
        $class = Invoice::calcStatusClass($model->invoice_status_id, $model->balance, $model->due_date_sql, $model->is_recurring);
        $label = Invoice::calcStatusLabel($model->invoice_status_name, $class, $this->entityType, $model->quote_invoice_id);

        if ($model->invoice_status_id == INVOICE_STATUS_SENT && (! $model->last_sent_date_sql || $model->last_sent_date_sql == '0000-00-00')) {
            $label = trans('texts.pending');
        }

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
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
                },
            ],
            [
                trans('texts.clone_invoice'),
                function ($model) {
                    return URL::to("invoices/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_INVOICE);
                },
            ],

        ];
    }
}
