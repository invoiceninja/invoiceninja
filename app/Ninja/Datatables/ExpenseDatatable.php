<?php

namespace App\Ninja\Datatables;

use App\Models\Expense;
use Auth;
use URL;
use Utils;

class ExpenseDatatable extends EntityDatatable
{
    public $entityType = ENTITY_EXPENSE;
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
            [
                'expense_date',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_EXPENSE, $model]))
                        return $this->addNote(link_to("expenses/{$model->public_id}/edit", Utils::fromSqlDate($model->expense_date_sql))->toHtml(), $model->private_notes);
                    else
                        return Utils::fromSqlDate($model->expense_date_sql);

                },
            ],
            [
                'amount',
                function ($model) {
                    $amount = $model->amount + Utils::calculateTaxes($model->amount, $model->tax_rate1, $model->tax_rate2);
                    $str = Utils::formatMoney($amount, $model->expense_currency_id);

                    // show both the amount and the converted amount
                    if ($model->exchange_rate != 1) {
                        $converted = round($amount * $model->exchange_rate, 2);
                        $str .= ' | ' . Utils::formatMoney($converted, $model->invoice_currency_id);
                    }

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
                    return $this->showWithTooltip($model->public_notes);
                },
            ],
            [
                'status',
                function ($model) {
                    return self::getStatusLabel($model->invoice_id, $model->should_be_invoiced, $model->balance, $model->payment_date);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_expense'),
                function ($model) {
                    return URL::to("expenses/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('view', [ENTITY_EXPENSE, $model]);
                },
            ],
            [
                trans("texts.clone_expense"),
                function ($model) {
                    return URL::to("expenses/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_EXPENSE);
                },
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("/invoices/{$model->invoice_public_id}/edit");
                },
                function ($model) {
                    return $model->invoice_public_id && Auth::user()->can('view', [ENTITY_INVOICE, $model]);
                },
            ],
            [
                trans('texts.invoice_expense'),
                function ($model) {
                    return "javascript:submitForm_expense('invoice', {$model->public_id})";
                },
                function ($model) {
                    return ! $model->invoice_id && (! $model->deleted_at || $model->deleted_at == '0000-00-00') && Auth::user()->can('create', ENTITY_INVOICE);
                },
            ],
        ];
    }

    private function getStatusLabel($invoiceId, $shouldBeInvoiced, $balance, $paymentDate)
    {
        $label = Expense::calcStatusLabel($shouldBeInvoiced, $invoiceId, $balance, $paymentDate);
        $class = Expense::calcStatusClass($shouldBeInvoiced, $invoiceId, $balance);

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }
}
