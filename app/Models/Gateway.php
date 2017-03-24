<?php

namespace App\Models;

use Eloquent;
use Omnipay;
use Utils;

/**
 * Class Gateway.
 */
class Gateway extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = true;

    protected $fillable = [
        'provider',
        'is_offsite',
        'sort_order',
    ];

    /**
     * @var array
     */
    public static $gatewayTypes = [
        GATEWAY_TYPE_CREDIT_CARD,
        GATEWAY_TYPE_BANK_TRANSFER,
        GATEWAY_TYPE_PAYPAL,
        GATEWAY_TYPE_BITCOIN,
        GATEWAY_TYPE_DWOLLA,
        GATEWAY_TYPE_TOKEN,
    ];

    // these will appear in the primary gateway select
    // the rest are shown when selecting 'more options'
    /**
     * @var array
     */
    public static $preferred = [
        GATEWAY_PAYPAL_EXPRESS,
        GATEWAY_BITPAY,
        GATEWAY_DWOLLA,
        GATEWAY_STRIPE,
        GATEWAY_BRAINTREE,
        GATEWAY_AUTHORIZE_NET,
        GATEWAY_MOLLIE,
        GATEWAY_CUSTOM,
    ];

    // allow adding these gateway if another gateway
    // is already configured
    /**
     * @var array
     */
    public static $alternate = [
        GATEWAY_PAYPAL_EXPRESS,
        GATEWAY_BITPAY,
        GATEWAY_DWOLLA,
        GATEWAY_CUSTOM,
    ];

    /**
     * @var array
     */
    public static $hiddenFields = [
        // PayPal
        'headerImageUrl',
        'solutionType',
        'landingPage',
        'brandName',
        'logoImageUrl',
        'borderColor',
        // Dwolla
        'returnUrl',
    ];

    /**
     * @var array
     */
    public static $optionalFields = [
        // PayPal
        'testMode',
        'developerMode',
        // Dwolla
        'sandbox',
    ];

    /**
     * @return string
     */
    public function getLogoUrl()
    {
        return '/images/gateways/logo_'.$this->provider.'.png';
    }

    /**
     * @param $gatewayId
     *
     * @return bool
     */
    public function isGateway($gatewayId)
    {
        return $this->id == $gatewayId;
    }

    /**
     * @param $type
     *
     * @return string
     */
    public static function getPaymentTypeName($type)
    {
        return Utils::toCamelCase(strtolower(str_replace('PAYMENT_TYPE_', '', $type)));
    }

    /**
     * @param $gatewayIds
     *
     * @return int
     */
    public static function hasStandardGateway($gatewayIds)
    {
        $diff = array_diff($gatewayIds, static::$alternate);

        return count($diff);
    }

    /**
     * @param $query
     * @param $accountGatewaysIds
     */
    public function scopePrimary($query, $accountGatewaysIds)
    {
        $query->where('payment_library_id', '=', 1)
            ->where('id', '!=', GATEWAY_WEPAY)
            ->whereIn('id', static::$preferred)
            ->whereIn('id', $accountGatewaysIds);
    }

    /**
     * @param $query
     * @param $accountGatewaysIds
     */
    public function scopeSecondary($query, $accountGatewaysIds)
    {
        $query->where('payment_library_id', '=', 1)
            ->where('id', '!=', GATEWAY_WEPAY)
            ->whereNotIn('id', static::$preferred)
            ->whereIn('id', $accountGatewaysIds);
    }

    /**
     * @return string|\Symfony\Component\Translation\TranslatorInterface
     */
    public function getHelp()
    {
        $link = '';

        if ($this->id == GATEWAY_AUTHORIZE_NET) {
            $link = 'http://reseller.authorize.net/application/?id=5560364';
        } elseif ($this->id == GATEWAY_PAYPAL_EXPRESS) {
            $link = 'https://www.paypal.com/us/cgi-bin/webscr?cmd=_login-api-run';
        } elseif ($this->id == GATEWAY_TWO_CHECKOUT) {
            $link = 'https://www.2checkout.com/referral?r=2c37ac2298';
        } elseif ($this->id == GATEWAY_BITPAY) {
            $link = 'https://bitpay.com/dashboard/signup';
        } elseif ($this->id == GATEWAY_DWOLLA) {
            $link = 'https://www.dwolla.com/register';
        } elseif ($this->id == GATEWAY_SAGE_PAY_DIRECT || $this->id == GATEWAY_SAGE_PAY_SERVER) {
            $link = 'https://applications.sagepay.com/apply/2C02C252-0F8A-1B84-E10D-CF933EFCAA99';
        }

        $key = 'texts.gateway_help_'.$this->id;
        $str = trans($key, [
            'link' => "<a href='$link' target='_blank'>Click here</a>",
            'complete_link' => url('/complete'),
        ]);

        return $key != $str ? $str : '';
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        if ($this->isCustom()) {
            return [
                'name' => '',
                'text' => '',
            ];
        } else {
            return Omnipay::create($this->provider)->getDefaultParameters();
        }
    }

    public function isCustom()
    {
        return $this->id === GATEWAY_CUSTOM;
    }
}
