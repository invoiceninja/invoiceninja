<?php

namespace App\Ninja\Datatables;

use Utils;

class ActivityDatatable extends EntityDatatable
{
    public $entityType = ENTITY_ACTIVITY;

    public function columns()
    {
        return [
            [
                'activities.id',
                function ($model) {
                    $str = Utils::timestampToDateTimeString(strtotime($model->created_at));

                    if ($model->contact_id) {
                        $ipLookUpLink = IP_LOOKUP_URL . $model->ip;
                        $str .= sprintf(' &nbsp; <i class="fa fa-globe" style="cursor:pointer" title="%s" onclick="openUrl(\'%s\', \'IP Lookup\')"></i>', $model->ip, $ipLookUpLink);
                    }

                    return $str;
                },
            ],
            [
                'activity_type_id',
                function ($model) {
                    $data = [
                        'client' => link_to('/clients/' . $model->client_public_id, Utils::getClientDisplayName($model))->toHtml(),
                        'user' => $model->is_system ? '<i>' . trans('texts.system') . '</i>' : Utils::getPersonDisplayName($model->user_first_name, $model->user_last_name, $model->user_email),
                        'invoice' => $model->invoice ? link_to('/invoices/' . $model->invoice_public_id, $model->is_recurring ? trans('texts.recurring_invoice') : $model->invoice)->toHtml() : null,
                        'quote' => $model->invoice ? link_to('/quotes/' . $model->invoice_public_id, $model->invoice)->toHtml() : null,
                        'contact' => $model->contact_id ? link_to('/clients/' . $model->client_public_id, Utils::getClientDisplayName($model))->toHtml() : Utils::getPersonDisplayName($model->user_first_name, $model->user_last_name, $model->user_email),
                        'payment' => $model->payment ?: '',
                        'credit' => $model->payment_amount ? Utils::formatMoney($model->credit, $model->currency_id, $model->country_id) : '',
                        'payment_amount' => $model->payment_amount ? Utils::formatMoney($model->payment_amount, $model->currency_id, $model->country_id) : null,
                        'adjustment' => $model->adjustment ? Utils::formatMoney($model->adjustment, $model->currency_id, $model->country_id) : null,
                        'task' => $model->task_public_id ? link_to('/tasks/' . $model->task_public_id, substr($model->task_description, 0, 30).'...') : null,
                        'expense' => $model->expense_public_id ? link_to('/expenses/' . $model->expense_public_id, substr($model->expense_public_notes, 0, 30).'...') : null,
                    ];

                    $str = trans("texts.activity_{$model->activity_type_id}", $data);

                    if ($model->notes) {
                        $str .= ' - ' . trans("texts.notes_{$model->notes}");
                    }

                    return $str;
                },
            ],
            [
                'balance',
                function ($model) {
                    return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                },
            ],
            [
                'adjustment',
                function ($model) {
                    return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id, $model->country_id) : '';
                },
            ],
        ];
    }
}
