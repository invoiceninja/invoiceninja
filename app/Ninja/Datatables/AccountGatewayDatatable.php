<?php

namespace App\Ninja\Datatables;

use App\Models\AccountGateway;
use App\Models\AccountGatewaySettings;
use App\Models\GatewayType;
use Cache;
use URL;
use Utils;

class AccountGatewayDatatable extends EntityDatatable
{
    private static $accountGateways;
    private static $accountGatewaySettings;

    public $entityType = ENTITY_ACCOUNT_GATEWAY;

    public function columns()
    {
        return [
            [
                'gateway',
                function ($model) {
                    if ($model->deleted_at) {
                        return $model->name;
                    } elseif ($model->gateway_id == GATEWAY_CUSTOM) {
                        $accountGateway = $this->getAccountGateway($model->id);
                        $name = $accountGateway->getConfigField('name') . ' [' . trans('texts.custom') . ']';

                        return link_to("gateways/{$model->public_id}/edit", $name)->toHtml();
                    } elseif ($model->gateway_id != GATEWAY_WEPAY) {
                        return link_to("gateways/{$model->public_id}/edit", $model->name)->toHtml();
                    } else {
                        $accountGateway = $this->getAccountGateway($model->id);
                        $config = $accountGateway->getConfig();
                        $endpoint = WEPAY_ENVIRONMENT == WEPAY_STAGE ? 'https://stage.wepay.com/' : 'https://www.wepay.com/';
                        $wepayAccountId = $config->accountId;
                        $wepayState = isset($config->state) ? $config->state : null;
                        $linkText = $model->name;
                        $url = $endpoint.'account/'.$wepayAccountId;
                        $html = link_to($url, $linkText, ['target' => '_blank'])->toHtml();

                        try {
                            if ($wepayState == 'action_required') {
                                $updateUri = $endpoint.'api/account_update/'.$wepayAccountId.'?redirect_uri='.urlencode(URL::to('gateways'));
                                $linkText .= ' <span style="color:#d9534f">('.trans('texts.action_required').')</span>';
                                $url = $updateUri;
                                $html = "<a href=\"{$url}\">{$linkText}</a>";
                                $model->setupUrl = $url;
                            } elseif ($wepayState == 'pending') {
                                $linkText .= ' ('.trans('texts.resend_confirmation_email').')';
                                $model->resendConfirmationUrl = $url = URL::to("gateways/{$accountGateway->public_id}/resend_confirmation");
                                $html = link_to($url, $linkText)->toHtml();
                            }
                        } catch (\WePayException $ex) {
                        }

                        return $html;
                    }
                },
            ],
            [
                'limit',
                function ($model) {
                    $gatewayTypes = $this->getGatewayTypes($model->id, $model->gateway_id);
                    $html = '';
                    foreach ($gatewayTypes as $gatewayTypeId) {
                        $accountGatewaySettings = $this->getAccountGatewaySetting($gatewayTypeId);
                        $gatewayType = Utils::getFromCache($gatewayTypeId, 'gatewayTypes');

                        if (count($gatewayTypes) > 1) {
                            if ($html) {
                                $html .= '<br>';
                            }
                            $html .= $gatewayType->name . ' &mdash; ';
                        }

                        if ($accountGatewaySettings && $accountGatewaySettings->min_limit !== null && $accountGatewaySettings->max_limit !== null) {
                            $html .= Utils::formatMoney($accountGatewaySettings->min_limit) . ' - ' . Utils::formatMoney($accountGatewaySettings->max_limit);
                        } elseif ($accountGatewaySettings && $accountGatewaySettings->min_limit !== null) {
                            $html .= trans('texts.min_limit',
                                ['min' => Utils::formatMoney($accountGatewaySettings->min_limit)]
                            );
                        } elseif ($accountGatewaySettings && $accountGatewaySettings->max_limit !== null) {
                            $html .= trans('texts.max_limit',
                                ['max' => Utils::formatMoney($accountGatewaySettings->max_limit)]
                            );
                        } else {
                            $html .= trans('texts.no_limit');
                        }
                    }

                    return $html;
                },
            ],
            [
                'fees',
                function ($model) {
                    if (! $model->gateway_fee_enabled) {
                        return trans('texts.fees_disabled');
                    }

                    $gatewayTypes = $this->getGatewayTypes($model->id, $model->gateway_id);
                    $html = '';
                    foreach ($gatewayTypes as $gatewayTypeId) {
                        $accountGatewaySettings = $this->getAccountGatewaySetting($gatewayTypeId);
                        if (! $accountGatewaySettings || ! $accountGatewaySettings->areFeesEnabled()) {
                            continue;
                        }

                        $gatewayType = Utils::getFromCache($gatewayTypeId, 'gatewayTypes');

                        if (count($gatewayTypes) > 1) {
                            if ($html) {
                                $html .= '<br>';
                            }
                            $html .= $gatewayType->name . ' &mdash; ';
                        }
                        $html .= $accountGatewaySettings->feesToString();

                        if ($accountGatewaySettings->hasTaxes()) {
                            $html .= ' + ' . trans('texts.tax');
                        }
                    };
                    return $html ?: trans('texts.no_fees');
                },
            ],
        ];
    }

    public function actions()
    {
        $actions = [
            [
                uctrans('texts.resend_confirmation_email'),
                function ($model) {
                    return $model->resendConfirmationUrl;
                },
                function ($model) {
                    return ! $model->deleted_at && $model->gateway_id == GATEWAY_WEPAY && ! empty($model->resendConfirmationUrl);
                },
            ], [
                uctrans('texts.edit_gateway'),
                function ($model) {
                    return URL::to("gateways/{$model->public_id}/edit");
                },
                function ($model) {
                    return ! $model->deleted_at;
                },
            ], [
                uctrans('texts.finish_setup'),
                function ($model) {
                    return $model->setupUrl;
                },
                function ($model) {
                    return ! $model->deleted_at && $model->gateway_id == GATEWAY_WEPAY && ! empty($model->setupUrl);
                },
            ], [
                uctrans('texts.manage_account'),
                function ($model) {
                    $accountGateway = $this->getAccountGateway($model->id);
                    $endpoint = WEPAY_ENVIRONMENT == WEPAY_STAGE ? 'https://stage.wepay.com/' : 'https://www.wepay.com/';

                    return [
                        'url' => $endpoint.'account/'.$accountGateway->getConfig()->accountId,
                        'attributes' => 'target="_blank"',
                    ];
                },
                function ($model) {
                    return ! $model->deleted_at && $model->gateway_id == GATEWAY_WEPAY;
                },
            ], [
                uctrans('texts.terms_of_service'),
                function ($model) {
                    return 'https://go.wepay.com/terms-of-service-us';
                },
                function ($model) {
                    return $model->gateway_id == GATEWAY_WEPAY;
                },
            ],
        ];

        foreach (Cache::get('gatewayTypes') as $gatewayType) {
            $actions[] = [
                trans('texts.set_limits_fees', ['gateway_type' => $gatewayType->name]),
                function () use ($gatewayType) {
                    return "javascript:showLimitsModal('{$gatewayType->name}', {$gatewayType->id})";
                },
                function ($model) use ($gatewayType) {
                    // Only show this action if the given gateway supports this gateway type
                    if ($model->gateway_id == GATEWAY_CUSTOM) {
                        return $gatewayType->id == GATEWAY_TYPE_CUSTOM;
                    } else {
                        $accountGateway = $this->getAccountGateway($model->id);
                        return $accountGateway->paymentDriver()->supportsGatewayType($gatewayType->id);
                    }
                },
            ];
        }

        return $actions;
    }

    private function getAccountGateway($id)
    {
        if (isset(static::$accountGateways[$id])) {
            return static::$accountGateways[$id];
        }

        static::$accountGateways[$id] = AccountGateway::find($id);

        return static::$accountGateways[$id];
    }

    private function getAccountGatewaySetting($gatewayTypeId)
    {
        if (isset(static::$accountGatewaySettings[$gatewayTypeId])) {
            return static::$accountGatewaySettings[$gatewayTypeId];
        }

        static::$accountGatewaySettings[$gatewayTypeId] = AccountGatewaySettings::scope()
            ->where('account_gateway_settings.gateway_type_id', '=', $gatewayTypeId)->first();

        return static::$accountGatewaySettings[$gatewayTypeId];
    }

    private function getGatewayTypes($id, $gatewayId)
    {
        if ($gatewayId == GATEWAY_CUSTOM) {
            $gatewayTypes = [GATEWAY_TYPE_CUSTOM];
        } else {
            $accountGateway = $this->getAccountGateway($id);
            $paymentDriver = $accountGateway->paymentDriver();
            $gatewayTypes = $paymentDriver->gatewayTypes();
            $gatewayTypes = array_diff($gatewayTypes, [GATEWAY_TYPE_TOKEN]);
        }

        return $gatewayTypes;
    }
}
