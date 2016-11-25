<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;

class ExpenseDatatable extends EntityDatatable
{
    public $entityType = ENTITY_EXPENSE;
    public $sortCol = 3;

    public function columns()
    {
        return [
            [
                'vendor_name',
                function ($model)
                {
                    if ($model->vendor_public_id) {
                        if(!Auth::user()->can('viewByOwner', [ENTITY_VENDOR, $model->vendor_user_id])){
                            return $model->vendor_name;
                        }

                        return link_to("vendors/{$model->vendor_public_id}", $model->vendor_name)->toHtml();
                    } else {
                        return '';
                    }
                },
                ! $this->hideClient
            ],
            [
                'client_name',
                function ($model)
                {
                    if ($model->client_public_id) {
                        if(!Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])){
                            return Utils::getClientDisplayName($model);
                        }

                        return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                    } else {
                        return '';
                    }
                },
                ! $this->hideClient
            ],
            [
                'expense_date',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_EXPENSE, $model->user_id])){
                        return Utils::fromSqlDate($model->expense_date);
                    }

                    return link_to("expenses/{$model->public_id}/edit", Utils::fromSqlDate($model->expense_date))->toHtml();
                }
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
                }
            ],
            [
                'category',
                function ($model) {
                    return $model->category != null ? substr($model->category, 0, 100) : '';
                }
            ],
            [
                'public_notes',
                function ($model) {
                    return $model->public_notes != null ? substr($model->public_notes, 0, 100) : '';
                }
            ],
            [
                'status',
                function ($model) {
                    return self::getStatusLabel($model->invoice_id, $model->should_be_invoiced, $model->balance);
                }
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_expense'),
                function ($model) {
                    return URL::to("expenses/{$model->public_id}/edit") ;
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_EXPENSE, $model->user_id]);
                }
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("/invoices/{$model->invoice_public_id}/edit");
                },
                function ($model) {
                    return $model->invoice_public_id && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->invoice_user_id]);
                }
            ],
            [
                trans('texts.invoice_expense'),
                function ($model) {
                    return "javascript:submitForm_expense('invoice', {$model->public_id})";
                },
                function ($model) {
                    return ! $model->invoice_id && (!$model->deleted_at || $model->deleted_at == '0000-00-00') && Auth::user()->can('create', ENTITY_INVOICE);
                }
            ],
        ];
    }


    private function getStatusLabel($invoiceId, $shouldBeInvoiced, $balance)
    {
        if ($invoiceId) {
            if (floatval($balance)) {
                $label = trans('texts.invoiced');
                $class = 'default';
            } else {
                $label = trans('texts.paid');
                $class = 'success';
            }
        } elseif ($shouldBeInvoiced) {
            $label = trans('texts.pending');
            $class = 'warning';
        } else {
            $label = trans('texts.logged');
            $class = 'primary';
        }

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }

}
