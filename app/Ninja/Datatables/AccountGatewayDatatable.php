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
                    $accountGateway = $this->getAccountGateway($model->id);
                    if ($model->deleted_at) {
                        return $model->name;
                    } elseif (in_array($model->gateway_id, [GATEWAY_CUSTOM1, GATEWAY_CUSTOM2, GATEWAY_CUSTOM3])) {
                        $name = $accountGateway->getConfigField('name') . ' [' . trans('texts.custom') . ']';
                        return link_to("gateways/{$model->public_id}/edit", $name)->toHtml();
                    } elseif ($model->gateway_id != GATEWAY_WEPAY) {
                        $name = $model->name;
                        if ($accountGateway->isTestMode()) {
                            $name .= sprintf(' [%s]', trans('texts.test'));
                        }
                        return link_to("gateways/{$model->public_id}/edit", $name)->toHtml();
                    } else {
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
                    if ($model->gateway_id == GATEWAY_CUSTOM1) {
                        return $gatewayType->id == GATEWAY_TYPE_CUSTOM1;
                    } elseif ($model->gateway_id == GATEWAY_CUSTOM2) {
                        return $gatewayType->id == GATEWAY_TYPE_CUSTOM2;
                    } elseif ($model->gateway_id == GATEWAY_CUSTOM3) {
                        return $gatewayType->id == GATEWAY_TYPE_CUSTOM3;
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
        if ($gatewayId == GATEWAY_CUSTOM1) {
            $gatewayTypes = [GATEWAY_TYPE_CUSTOM1];
        } elseif ($gatewayId == GATEWAY_CUSTOM2) {
            $gatewayTypes = [GATEWAY_TYPE_CUSTOM2];
        } elseif ($gatewayId == GATEWAY_CUSTOM3) {
            $gatewayTypes = [GATEWAY_TYPE_CUSTOM3];
        } else {
            $accountGateway = $this->getAccountGateway($id);
            $paymentDriver = $accountGateway->paymentDriver();
            $gatewayTypes = $paymentDriver->gatewayTypes();
            $gatewayTypes = array_diff($gatewayTypes, [GATEWAY_TYPE_TOKEN]);
        }

        return $gatewayTypes;
    }
}
