<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;

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
                    if(!Auth::user()->can('viewByOwner', [ENTITY_INVOICE, $model->user_id])){
                        return $model->invoice_number;
                    }

                    return link_to("{$entityType}s/{$model->public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                }
            ],
            [
                'client_name',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])){
                        return Utils::getClientDisplayName($model);
                    }
                    return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                },
                ! $this->hideClient
            ],
            [
                'date',
                function ($model) {
                    return Utils::fromSqlDate($model->date);
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                }
            ],
            [
                'balance',
                function ($model) {
                    return $model->partial > 0 ?
                        trans('texts.partial_remaining', [
                            'partial' => Utils::formatMoney($model->partial, $model->currency_id, $model->country_id),
                            'balance' => Utils::formatMoney($model->balance, $model->currency_id, $model->country_id)]
                        ) :
                        Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
                },
                $entityType == ENTITY_INVOICE
            ],
            [
                $entityType == ENTITY_INVOICE ? 'due_date' : 'valid_until',
                function ($model) {
                    return Utils::fromSqlDate($model->due_date);
                },
            ],
            [
                'status',
                function ($model) use ($entityType) {
                    return $model->quote_invoice_id ? link_to("invoices/{$model->quote_invoice_id}/edit", trans('texts.converted'))->toHtml() : self::getStatusLabel($model);
                }
            ]
        ];
    }

    public function actions()
    {
        $entityType = $this->entityType;

        return [
            [
                trans("texts.edit_{$entityType}"),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans("texts.clone_{$entityType}"),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('create', ENTITY_INVOICE);
                }
            ],
            [
                trans('texts.view_history'),
                function ($model) use ($entityType) {
                    return URL::to("{$entityType}s/{$entityType}_history/{$model->public_id}");
                }
            ],
            [
                '--divider--', function(){return false;},
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]) || Auth::user()->can('create', ENTITY_PAYMENT);
                }
            ],
            [
                trans('texts.mark_sent'),
                function ($model) use ($entityType) {
                    return "javascript:submitForm_{$entityType}('markSent', {$model->public_id})";
                },
                function ($model) {
                    return $model->invoice_status_id < INVOICE_STATUS_SENT && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans('texts.mark_paid'),
                function ($model) use ($entityType) {
                    return "javascript:submitForm_{$entityType}('markPaid', {$model->public_id})";
                },
                function ($model) {
                    return $model->balance > 0 && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans('texts.enter_payment'),
                function ($model) {
                    return URL::to("payments/create/{$model->client_public_id}/{$model->public_id}");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE && $model->balance > 0 && Auth::user()->can('create', ENTITY_PAYMENT);
                }
            ],
            [
                trans('texts.view_quote'),
                function ($model) {
                    return URL::to("quotes/{$model->quote_id}/edit");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_INVOICE && $model->quote_id && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("invoices/{$model->quote_invoice_id}/edit");
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && $model->quote_invoice_id && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ],
            [
                trans('texts.convert_to_invoice'),
                function ($model) {
                    return "javascript:submitForm_quote('convert', {$model->public_id})";
                },
                function ($model) use ($entityType) {
                    return $entityType == ENTITY_QUOTE && ! $model->quote_invoice_id && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->user_id]);
                }
            ]
        ];
    }

    private function getStatusLabel($model)
    {
        $entityType = $this->entityType;

        // check if invoice is overdue
        if (Utils::parseFloat($model->balance) && $model->due_date && $model->due_date != '0000-00-00') {
            if (\DateTime::createFromFormat('Y-m-d', $model->due_date) < new \DateTime('now')) {
                $label = $entityType == ENTITY_INVOICE ? trans('texts.overdue') : trans('texts.expired');
                return '<h4><div class="label label-danger">' . $label . '</div></h4>';
            }
        }

        $label = trans('texts.status_' . strtolower($model->invoice_status_name));
        $class = 'default';
        switch ($model->invoice_status_id) {
            case INVOICE_STATUS_SENT:
                $class = 'info';
                break;
            case INVOICE_STATUS_VIEWED:
                $class = 'warning';
                break;
            case INVOICE_STATUS_APPROVED:
                $class = 'success';
                break;
            case INVOICE_STATUS_PARTIAL:
                $class = 'primary';
                break;
            case INVOICE_STATUS_PAID:
                $class = 'success';
                break;
        }

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }

    public function bulkActions()
    {
        $actions = parent::bulkActions();

        if ($this->entityType == ENTITY_INVOICE || $this->entityType == ENTITY_QUOTE) {
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

        return $actions;
    }
}
