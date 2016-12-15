<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;
use App\Models\PaymentMethod;

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
                    if(!Auth::user()->can('viewByOwner', [ENTITY_INVOICE, $model->invoice_user_id])){
                        return $model->invoice_number;
                    }

                    return link_to("invoices/{$model->invoice_public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                }
            ],
            [
                'client_name',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])){
                        return Utils::getClientDisplayName($model);
                    }

                    return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml() : '';
                },
                ! $this->hideClient
            ],
            [
                'transaction_reference',
                function ($model) {
                    return $model->transaction_reference ? $model->transaction_reference : '<i>'.trans('texts.manual_entry').'</i>';
                }
            ],
            [
                'method',
                function ($model) {
                    return ($model->payment_type && !$model->last4) ? $model->payment_type : ($model->account_gateway_id ? $model->gateway_name : '');
                }
            ],
            [
                'source',
                function ($model) {
                    $code = str_replace(' ', '', strtolower($model->payment_type));
                    $card_type = trans('texts.card_' . $code);
                    if ($model->payment_type_id != PAYMENT_TYPE_ACH) {
                        if($model->last4) {
                            $expiration = Utils::fromSqlDate($model->expiration, false)->format('m/y');
                            return '<img height="22" src="' . URL::to('/images/credit_cards/' . $code . '.png') . '" alt="' . htmlentities($card_type) . '">&nbsp; &bull;&bull;&bull;' . $model->last4 . ' ' . $expiration;
                        } elseif ($model->email) {
                            return $model->email;
                        }
                    } elseif ($model->last4) {
                        if($model->bank_name) {
                            $bankName = $model->bank_name;
                        } else {
                            $bankData = PaymentMethod::lookupBankData($model->routing_number);
                            if($bankData) {
                                $bankName = $bankData->name;
                            }
                        }
                        if (!empty($bankName)) {
                            return $bankName.'&nbsp; &bull;&bull;&bull;' . $model->last4;
                        } elseif($model->last4) {
                            return '<img height="22" src="' . URL::to('/images/credit_cards/ach.png') . '" alt="' . htmlentities($card_type) . '">&nbsp; &bull;&bull;&bull;' . $model->last4;
                        }
                    }
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                }
            ],
            [
                'payment_date',
                function ($model) {
                    if ($model->is_deleted) {
                        return Utils::dateToString($model->payment_date);
                    } else {
                        return link_to("payments/{$model->public_id}/edit", Utils::dateToString($model->payment_date))->toHtml();
                    }
                }
            ],
            [
                'payment_status_name',
                function ($model) {
                    return self::getStatusLabel($model);
                }
            ]
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
                    return Auth::user()->can('editByOwner', [ENTITY_PAYMENT, $model->user_id]);
                }
            ],
            [
                trans('texts.refund_payment'),
                function ($model) {
                    $max_refund = number_format($model->amount - $model->refunded, 2);
                    $formatted = Utils::formatMoney($max_refund, $model->currency_id, $model->country_id);
                    $symbol = Utils::getFromCache($model->currency_id ? $model->currency_id : 1, 'currencies')->symbol ;
                    return "javascript:showRefundModal({$model->public_id}, '{$max_refund}', '{$formatted}', '{$symbol}')";
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PAYMENT, $model->user_id])
                        && $model->payment_status_id >= PAYMENT_STATUS_COMPLETED
                        && $model->refunded < $model->amount
                        && $model->transaction_reference
                        && in_array($model->gateway_id , static::$refundableGateways);
                }
            ]
        ];
    }

    private function getStatusLabel($model)
    {
        $label = trans('texts.status_' . strtolower($model->payment_status_name));
        $class = 'default';
        switch ($model->payment_status_id) {
            case PAYMENT_STATUS_PENDING:
                $class = 'info';
                break;
            case PAYMENT_STATUS_COMPLETED:
                $class = 'success';
                break;
            case PAYMENT_STATUS_FAILED:
                $class = 'danger';
                break;
            case PAYMENT_STATUS_PARTIALLY_REFUNDED:
                $label = trans('texts.status_partially_refunded_amount', [
                    'amount' => Utils::formatMoney($model->refunded, $model->currency_id, $model->country_id),
                ]);
                $class = 'primary';
                break;
            case PAYMENT_STATUS_VOIDED:
            case PAYMENT_STATUS_REFUNDED:
                $class = 'default';
                break;
        }
        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }
}
