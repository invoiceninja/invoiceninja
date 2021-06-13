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

namespace App\PaymentDrivers\WePay;

use App\PaymentDrivers\WePayPaymentDriver;

class CreditCard
{
    public $wepay;

    public function __construct(WePayPaymentDriver $wepay)
    {
        $this->wepay = $wepay;
    }

    public function authorizeView($data)
    {
        $data['gateway'] = $this->wepay;
        
        return render('gateways.wepay.authorize.authorize', $data);
    }
 

 	public function authorizeResponse($data)
 	{
 		//https://developer.wepay.com/api/api-calls/credit_card#authorize

		// authorize the credit card
		$response = $this->wepay->request('credit_card/authorize', array(
		    'client_id'          => $account_id,
		    'client_secret'      => 'A vacation home rental',
		    'credit_card_id'     => 'goods',
		));

		// display the response
		print_r($response);
 	}   
}
