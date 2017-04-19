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
                        if (! Auth::user()->can('viewByOwner', [ENTITY_VENDOR, $model->vendor_user_id])) {
                            return $model->vendor_name;
                        }

                        return link_to("vendors/{$model->vendor_public_id}", $model->vendor_name)->toHtml();
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
                        if (! Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])) {
                            return Utils::getClientDisplayName($model);
                        }

                        return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                    } else {
                        return '';
                    }
                },
                ! $this->hideClient,
            ],
            [
                'expense_date',
                function ($model) {
                    if (! Auth::user()->can('viewByOwner', [ENTITY_EXPENSE, $model->user_id])) {
                        return Utils::fromSqlDate($model->expense_date_sql);
                    }

                    return link_to("expenses/{$model->public_id}/edit", Utils::fromSqlDate($model->expense_date_sql))->toHtml();
                },
            ],
            [
                'amount',
                function ($model) {
                    $amount = Utils::calculateTaxes($model->amount, $model->tax_rate1, $model->tax_rate2);
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
                    if (! Auth::user()->can('editByOwner', [ENTITY_EXPENSE_CATEGORY, $model->category_user_id])) {
                        return $category;
                    }

                    return $model->category_public_id ? link_to("expense_categories/{$model->category_public_id}/edit", $category)->toHtml() : '';
                },
            ],
            [
                'public_notes',
                function ($model) {
                    return $model->public_notes != null ? substr($model->public_notes, 0, 100) : '';
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
                    return Auth::user()->can('editByOwner', [ENTITY_EXPENSE, $model->user_id]);
                },
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("/invoices/{$model->invoice_public_id}/edit");
                },
                function ($model) {
                    return $model->invoice_public_id && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->invoice_user_id]);
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
