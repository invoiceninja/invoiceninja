<?php

namespace App\Ninja\Datatables;

use App\Models\Invoice;
use Auth;
use URL;
use Utils;

class InvoiceDatatable extends EntityDatatable
{
    public $entityType = ENTITY_INVOICE;
    public $sortCol = 3;

    public function columns()
    {
        $entityType = $this->entityType;

        return [
            [
                $entityType == ENTITY_INVOICE ? 'invoice_number' : 'quote_number',
                function ($model) use ($entityType) {
                    if(Auth::user()->viewModel($model, $entityType)) {
                        $str = link_to("{$entityType}s/{$model->public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                        return $this->addNote($str, $model->private_notes);
                    }
                    else
                        return $model->invoice_number;


                },
            ],
            [
                'client_name',
                function ($model) {
                    if(Auth::user()->can('view', [ENTITY_CLIENT, $model]))
                        return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                    else
                        return Utils::getClientDisplayName($model);

                },
                ! $this->hideClient,
            ],
            [
                'date',
                function ($model) {
                    return Utils::fromSqlDate($model->invoice_date);
                },
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                },
            ],
            [
                'balance',
                function ($model) {
                    return $model->partial > 0 ?
                        trans('texts.partial_remaining', [
                            'partial' => Utils::formatMoney($model->partial, $model->currency_id, $model->country_id),
                            'balance' => Utils::formatMoney($model->balance, $model->currency_id, $model->country_id), ]
                        ) :
                        Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                },
                $entityType == ENTITY_INVOICE,
            ],
            [
                $entityType == ENTITY_INVOICE ? 'due_date' : 'valid_until',
                function ($model) {
                    $str = '';
                    if ($model->partial_due_date) {
                        $str = Utils::fromSqlDate($model->partial_due_date);
                        if ($model->due_date_sql && $model->due_date_sql != '0000-00-00') {
                            $str .= ', ';
                        }
                    }
                    return $str . Utils::fromSqlDate($model->due_date_sql);
                },
            ],
            [
                'status',
                function ($model) use ($entityType) {
                    return $model->quote_invoice_id ? link_to("invoices/{$model->quote_invoice_id}/edit", trans('texts.converted'))->toHtml() : self::getStatusLabel($model);
                },
            ],
        ];
    }

    public function actions()
    {
        $entityType = $this->entityType;

        return [
            [
                trans("texts.clone_invoice"),
                function ($model) {
                    return URL::to("invoices/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_INVOICE);
                },
            ],
            [
                trans("texts.clone_quote"),
                function ($model) {
                    return URL::to("quotes/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_QUOTE);
                },
            ],
            [
                trans("texts.{$entityType}_history"),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$entityType}_history/{$model->public_id}");
                },
            ],
            [
                trans('texts.delivery_note'),
                function ($model) use ($entityType) {
                    return url("invoices/delivery_note/{$model->public_id}");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE;
                },
            ],
            [
                '--divider--', function () {
                    return false;
                },
                function ($model) {
                    return Auth::user()->canCreateOrEdit(ENTITY_INVOICE);
                },
            ],
            [
                trans('texts.mark_sent'),
                function ($model) use ($entityType) {
                    return "javascript:submitForm_{$entityType}('markSent', {$model->public_id})";
                },
                function ($model) {
                    return ! $model->is_public && Auth::user()->can('edit', [ENTITY_INVOICE, $model]);
                },
            ],
            [
                trans('texts.mark_paid'),
                function ($model) use ($entityType) {
                    return "javascript:submitForm_{$entityType}('markPaid', {$model->public_id})";
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE && $model->invoice_status_id != INVOICE_STATUS_PAID && Auth::user()->can('edit', [ENTITY_INVOICE, $model]);
                },
            ],
            [
                trans('texts.enter_payment'),
                function ($model) {
                    return URL::to("payments/create/{$model->client_public_id}/{$model->public_id}");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE && $model->invoice_status_id != INVOICE_STATUS_PAID && Auth::user()->can('create', ENTITY_PAYMENT);
                },
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("invoices/{$model->quote_invoice_id}/edit");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && $model->quote_invoice_id && Auth::user()->can('view', [ENTITY_INVOICE, $model]);
                },
            ],
            [
                trans('texts.new_proposal'),
                function ($model) {
                    return URL::to("proposals/create/{$model->public_id}");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && ! $model->quote_invoice_id && $model->invoice_status_id < INVOICE_STATUS_APPROVED && Auth::user()->can('create', ENTITY_PROPOSAL);
                },
            ],
            [
                trans('texts.convert_to_invoice'),
                function ($model) {
                    return "javascript:submitForm_quote('convert', {$model->public_id})";
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && ! $model->quote_invoice_id && Auth::user()->can('edit', [ENTITY_INVOICE, $model]);
                },
            ],
        ];
    }

    private function getStatusLabel($model)
    {
        $class = Invoice::calcStatusClass($model->invoice_status_id, $model->balance, $model->partial_due_date ?: $model->due_date_sql, $model->is_recurring);
        $label = Invoice::calcStatusLabel($model->invoice_status_name, $class, $this->entityType, $model->quote_invoice_id);

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }

    public function bulkActions()
    {
        $actions = [];

        if ($this->entityType == ENTITY_INVOICE || $this->entityType == ENTITY_QUOTE) {
            $actions[] = [
                'label' => mtrans($this->entityType, 'download_' . $this->entityType),
                'url' => 'javascript:submitForm_'.$this->entityType.'("download")',
            ];
            if (auth()->user()->isTrusted()) {
                $actions[] = [
                    'label' => mtrans($this->entityType, 'email_' . $this->entityType),
                    'url' => 'javascript:submitForm_'.$this->entityType.'("emailInvoice")',
                ];
            }
            $actions[] = \DropdownButton::DIVIDER;
            $actions[] = [
                'label' => mtrans($this->entityType, 'mark_sent'),
                'url' => 'javascript:submitForm_'.$this->entityType.'("markSent")',
            ];
        }

        if ($this->entityType == ENTITY_INVOICE) {
            $actions[] = [
                'label' => mtrans($this->entityType, 'mark_paid'),
                'url' => 'javascript:submitForm_'.$this->entityType.'("markPaid")',
            ];
        }

        $actions[] = \DropdownButton::DIVIDER;
        $actions = array_merge($actions, parent::bulkActions());

        return $actions;
    }
}
