<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Omnipay\Omnipay;

class Gateway extends StaticModel
{
    protected $casts = [
        'is_offsite' => 'boolean',
        'is_secure' => 'boolean',
        'recommended' => 'boolean',
        //'visible' => 'boolean',
        'sort_order' => 'int',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'default_gateway_type_id' => 'string',
        'fields' => 'json',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

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

}
