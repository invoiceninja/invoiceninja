<?php

namespace App\Ninja\Datatables;

use App\Models\Payment;
use App\Models\PaymentMethod;
use Auth;
use URL;
use Utils;

class PaymentDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PAYMENT;
    public $sortCol = 7;

    protected static $refundableGateways = [
        GATEWAY_STRIPE,
        GATEWAY_BRAINTREE,
        GATEWAY_WEPAY,
    ];

    public function columns()
    {
        return [
            [
                'invoice_name',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_INVOICE, $model->invoice_user_id]))
                        return link_to("invoices/{$model->invoice_public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                    else
                        return $model->invoice_number;

                    },
            ],
            [
                'client_name',
                function ($model) {
                    if(Auth::user()->can('view', [ENTITY_CLIENT, ENTITY_CLIENT]))
                        return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml() : '';
                    else
                        return Utils::getClientDisplayName($model);


                },
                ! $this->hideClient,
            ],
            [
                'transaction_reference',
                function ($model) {
                    $str = $model->transaction_reference ? e($model->transaction_reference) : '<i>'.trans('texts.manual_entry').'</i>';
                    return $this->addNote($str, $model->private_notes);
                },
            ],
            [
                'method',
                function ($model) {
                    return $model->account_gateway_id ? $model->gateway_name : ($model->payment_type ? trans('texts.payment_type_' . $model->payment_type) : '');
                },
            ],
            [
                'source',
                function ($model) {
                    $code = str_replace(' ', '', strtolower($model->payment_type));
                    $card_type = trans('texts.card_' . $code);
                    if ($model->payment_type_id != PAYMENT_TYPE_ACH) {
                        if ($model->last4) {
                            $expiration = Utils::fromSqlDate($model->expiration, false)->format('m/y');

                            return '<img height="22" src="' . URL::to('/images/credit_cards/' . $code . '.png') . '" alt="' . htmlentities($card_type) . '">&nbsp; &bull;&bull;&bull;' . $model->last4 . ' ' . $expiration;
                        } elseif ($model->email) {
                            return $model->email;
                        } elseif ($model->payment_type) {
                            return trans('texts.payment_type_' . $model->payment_type);
                        }
                    } elseif ($model->last4) {
                        if ($model->bank_name) {
                            $bankName = $model->bank_name;
                        } else {
                            $bankData = PaymentMethod::lookupBankData($model->routing_number);
                            if ($bankData) {
                                $bankName = $bankData->name;
                            }
                        }
                        if (! empty($bankName)) {
                            return $bankName.'&nbsp; &bull;&bull;&bull;' . $model->last4;
                        } elseif ($model->last4) {
                            return '<img height="22" src="' . URL::to('/images/credit_cards/ach.png') . '" alt="' . htmlentities($card_type) . '">&nbsp; &bull;&bull;&bull;' . $model->last4;
                        }
                    }
                },
            ],
            [
                'amount',
                function ($model) {
                    $amount = Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);

                    if ($model->exchange_currency_id && $model->exchange_rate != 1) {
                        $amount .= ' | ' . Utils::formatMoney($model->amount * $model->exchange_rate, $model->exchange_currency_id, $model->country_id);
                    }

                    return $amount;
                },
            ],
            [
                'date',
                function ($model) {
                    if ($model->is_deleted) {
                        return Utils::dateToString($model->payment_date);
                    } else {
                        return link_to("payments/{$model->public_id}/edit", Utils::dateToString($model->payment_date))->toHtml();
                    }
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

    public function actions()
    {
        return [
            [
                trans('texts.edit_payment'),
                function ($model) {
                    return URL::to("payments/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('view', [ENTITY_PAYMENT, $model]);
                },
            ],
            [
                trans('texts.email_payment'),
                function ($model) {
                    return "javascript:submitForm_payment('email', {$model->public_id})";
                },
                function ($model) {
                    return Auth::user()->can('edit', [ENTITY_PAYMENT, $model]);
                },
            ],
            [
                trans('texts.refund_payment'),
                function ($model) {
                    $max_refund = $model->amount - $model->refunded;
                    $formatted = Utils::formatMoney($max_refund, $model->currency_id, $model->country_id);
                    $symbol = Utils::getFromCache($model->currency_id ? $model->currency_id : 1, 'currencies')->symbol;
                    $local = in_array($model->gateway_id, [GATEWAY_BRAINTREE, GATEWAY_STRIPE, GATEWAY_WEPAY]) || ! $model->gateway_id ? 0 : 1;

                    return "javascript:showRefundModal({$model->public_id}, '{$max_refund}', '{$formatted}', '{$symbol}', {$local})";
                },
                function ($model) {
                    return Auth::user()->can('edit', [ENTITY_PAYMENT, $model])
                        && $model->payment_status_id >= PAYMENT_STATUS_COMPLETED
                        && $model->refunded < $model->amount;
                },
            ],
        ];
    }

    private function getStatusLabel($model)
    {
        $amount = Utils::formatMoney($model->refunded, $model->currency_id, $model->country_id);
        $label = Payment::calcStatusLabel($model->payment_status_id, $model->status, $amount);
        $class = Payment::calcStatusClass($model->payment_status_id);

        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }
}
