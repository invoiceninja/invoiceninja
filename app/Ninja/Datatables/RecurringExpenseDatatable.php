<?php

namespace App\Ninja\Datatables;

use App\Models\Expense;
use Auth;
use URL;
use Utils;

class RecurringExpenseDatatable extends EntityDatatable
{
    public $entityType = ENTITY_RECURRING_EXPENSE;
    public $sortCol = 3;

    public function columns()
    {
        return [
            [
                'vendor_name',
                function ($model) {
                    if ($model->vendor_public_id) {
                        if (Auth::user()->can('view', [ENTITY_VENDOR, $model]))
                            return link_to("vendors/{$model->vendor_public_id}", $model->vendor_name)->toHtml();
                        else
                            return $model->vendor_name;

                    } else {
                        return '';
                    }
                },
                ! $this->hideClient,
            ],
            [
                'client_name',
                function ($model) {
                    if ($model->client_public_id) {
                        if (Auth::user()->can('view', [ENTITY_CLIENT, $model]))
                            return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                        else
                            return Utils::getClientDisplayName($model);


                    } else {
                        return '';
                    }
                },
                ! $this->hideClient,
            ],
/*
            [
                'expense_date',
                function ($model) {
                    if (! Auth::user()->can('viewByOwner', [ENTITY_EXPENSE, $model->user_id])) {
                        return Utils::fromSqlDate($model->expense_date_sql);
                    }

                    return link_to("expenses/{$model->public_id}/edit", Utils::fromSqlDate($model->expense_date_sql))->toHtml();
                },
            ],
*/
            [
                'frequency',
                function ($model) {
                    $frequency = strtolower($model->frequency);
                    $frequency = preg_replace('/\s/', '_', $frequency);

                    $str = link_to("recurring_expenses/{$model->public_id}/edit", trans('texts.freq_'.$frequency))->toHtml();
                    return $this->addNote($str, $model->private_notes);
                },
            ],
            [
                'amount',
                function ($model) {
                    $amount = $model->amount + Utils::calculateTaxes($model->amount, $model->tax_rate1, $model->tax_rate2);
                    $str = Utils::formatMoney($amount, $model->expense_currency_id);

                    /*
                    // show both the amount and the converted amount
                    if ($model->exchange_rate != 1) {
                        $converted = round($amount * $model->exchange_rate, 2);
                        $str .= ' | ' . Utils::formatMoney($converted, $model->invoice_currency_id);
                    }
                    */

                    return $str;
                },
            ],
            [
                'category',
                function ($model) {
                    $category = $model->category != null ? substr($model->category, 0, 100) : '';
                    if (Auth::user()->can('view', [ENTITY_EXPENSE_CATEGORY, $model]))
                        return $model->category_public_id ? link_to("expense_categories/{$model->category_public_id}/edit", $category)->toHtml() : '';
                    else
                        return $category;

                },
            ],
            [
                'public_notes',
                function ($model) {
                    return $this->showWithTooltip($model->public_notes, 100);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_recurring_expense'),
                function ($model) {
                    return URL::to("recurring_expenses/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('view', [ENTITY_RECURRING_EXPENSE, $model]);
                },
            ],
        ];
    }

}
