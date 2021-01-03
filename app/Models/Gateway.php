<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

class Gateway extends StaticModel
{
    protected $casts = [
        'is_offsite' => 'boolean',
        'is_secure' => 'boolean',
        'recommended' => 'boolean',
        'visible' => 'boolean',
        'sort_order' => 'int',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'default_gateway_type_id' => 'string',
        // 'fields' => 'json',
        'fields' => 'object',
        'options' => 'array',
    ];

    protected $appends = [
        'options',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function getOptionsAttribute()
    {
        return $this->getMethods();
    }

    /**
     * Test if gateway is custom.
     * @return bool TRUE|FALSE
     */
    public function isCustom() :bool
    {
        return in_array($this->id, [62, 67, 68]); //static table ids of the custom gateways
    }

    public function getHelp()
    {
        $link = '';

        if ($this->id == 1) {
            $link = 'http://reseller.authorize.net/application/?id=5560364';
        } elseif ($this->id == 15) {
            $link = 'https://www.paypal.com/us/cgi-bin/webscr?cmd=_login-api-run';
        } elseif ($this->id == 24) {
            $link = 'https://www.2checkout.com/referral?r=2c37ac2298';
        } elseif ($this->id == 35) {
            $link = 'https://bitpay.com/dashboard/signup';
        } elseif ($this->id == 18) {
            $link = 'https://applications.sagepay.com/apply/2C02C252-0F8A-1B84-E10D-CF933EFCAA99';
        } elseif ($this->id == 20) {
            $link = 'https://dashboard.stripe.com/account/apikeys';
        }

        // $key = 'texts.gateway_help_'.$this->id;
        // $str = trans($key, [
        //     'link' => "<a href='$link' >Click here</a>",
        //     'complete_link' => url('/complete'),
        // ]);

        return $link;
        
        //return $key != $str ? $str : '';
    }


    /**
     * Returns an array of methods and the gatewaytypes possible
     *
     * @return array
     *///todo remove methods replace with gatewaytype:: and then nest refund / token billing
    public function getMethods()
    {
        switch ($this->id) {
            case 1:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true]];//Authorize.net
                break;
            case 15:
                return [GatewayType::PAYPAL => ['refund' => true, 'token_billing' => false]]; //Paypal
                break;
            case 20:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true],
                        GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true],
                        GatewayType::ALIPAY => ['refund' => false, 'token_billing' => false],
                        GatewayType::APPLE_PAY => ['refund' => false, 'token_billing' => false]]; //Stripe
                break;
            case 39:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true]]; //Checkout
                break;
            default:
                return [];
                break;
        }
    }
}
