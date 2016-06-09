<?php namespace App\Models;

use Eloquent;
use Omnipay;
use Utils;

class Gateway extends Eloquent
{
    public $timestamps = true;

    // these will appear in the primary gateway select
    // the rest are shown when selecting 'more options'
    public static $preferred = [
        GATEWAY_PAYPAL_EXPRESS,
        GATEWAY_BITPAY,
        GATEWAY_DWOLLA,
        GATEWAY_STRIPE,
        GATEWAY_BRAINTREE,
    ];

    // allow adding these gateway if another gateway
    // is already configured
    public static $alternate = [
        GATEWAY_PAYPAL_EXPRESS,
        GATEWAY_BITPAY,
        GATEWAY_DWOLLA,
    ];

    // TODO remove this
    public static $paymentTypes = [
        PAYMENT_TYPE_STRIPE,
        PAYMENT_TYPE_CREDIT_CARD,
        PAYMENT_TYPE_PAYPAL,
        PAYMENT_TYPE_BITCOIN,
        PAYMENT_TYPE_DIRECT_DEBIT,
        PAYMENT_TYPE_DWOLLA,
    ];

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

    public static $optionalFields = [
        // PayPal
        'testMode',
        'developerMode',
        // Dwolla
        'sandbox',
    ];

    public function getLogoUrl()
    {
        return '/images/gateways/logo_'.$this->provider.'.png';
    }

    public function isGateway($gatewayId)
    {
        return $this->id == $gatewayId;
    }

    public static function getPaymentTypeName($type)
    {
        return Utils::toCamelCase(strtolower(str_replace('PAYMENT_TYPE_', '', $type)));
    }

    public static function hasStandardGateway($gatewayIds)
    {
        $diff = array_diff($gatewayIds, static::$alternate);

        return count($diff);
    }

    public function scopePrimary($query, $accountGatewaysIds)
    {
        $query->where('payment_library_id', '=', 1)
            ->where('id', '!=', GATEWAY_WEPAY)
            ->whereIn('id', Gateway::$preferred)
            ->whereNotIn('id', $accountGatewaysIds);

        // if the user has a credit card gateway only show alternate options
        if (static::hasStandardGateway($accountGatewaysIds)) {
            $query->whereNotIn('id', array_diff(static::$preferred, static::$alternate));
        }
    }

    public function scopeSecondary($query, $accountGatewaysIds)
    {
        // if the user has a credit card don't show an secondary options
        if (static::hasStandardGateway($accountGatewaysIds)) {
            $query->where('id', '=', 0);
        } else {
            $query->where('payment_library_id', '=', 1)
                ->where('id', '!=', GATEWAY_WEPAY)
                ->whereNotIn('id', static::$preferred);
        }
    }

    /*
    public static function getPaymentTypeLinks() {
        $data = [];
        foreach (self::$paymentTypes as $type) {
            $data[] = Utils::toCamelCase(strtolower(str_replace('PAYMENT_TYPE_', '', $type)));
        }
        return $data;
    }
    */

    public function getHelp()
    {
        $link = '';

        if ($this->id == GATEWAY_AUTHORIZE_NET || $this->id == GATEWAY_AUTHORIZE_NET_SIM) {
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
        $str = trans($key, ['link' => "<a href='$link' target='_blank'>Click here</a>"]);

        return $key != $str ? $str : '';
    }

    public function getFields()
    {
        return Omnipay::create($this->provider)->getDefaultParameters();
    }

    public static function getPaymentType($gatewayId) {
        if ($gatewayId == GATEWAY_PAYPAL_EXPRESS) {
            return PAYMENT_TYPE_PAYPAL;
        } else if ($gatewayId == GATEWAY_BITPAY) {
            return PAYMENT_TYPE_BITCOIN;
        } else if ($gatewayId == GATEWAY_DWOLLA) {
            return PAYMENT_TYPE_DWOLLA;
        } else if ($gatewayId == GATEWAY_GOCARDLESS) {
            return PAYMENT_TYPE_DIRECT_DEBIT;
        } else if ($gatewayId == GATEWAY_STRIPE) {
            return PAYMENT_TYPE_STRIPE;
        } else {
            return PAYMENT_TYPE_CREDIT_CARD;
        }
    }

    public static function getPrettyPaymentType($gatewayId) {
        return trans('texts.' . strtolower(Gateway::getPaymentType($gatewayId)));
    }
}
