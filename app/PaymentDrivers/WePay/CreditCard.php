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
    public $wepay_payment_driver;

    public function __construct(WePayPaymentDriver $wepay_payment_driver)
    {
        $this->wepay_payment_driver = $wepay_payment_driver;
    }

    public function authorizeView($data)
    {
        $data['gateway'] = $this->wepay_payment_driver;
        
        return render('gateways.wepay.authorize.authorize', $data);
    }

 	public function authorizeResponse($request)
 	{
 		//https://developer.wepay.com/api/api-calls/credit_card#authorize

        $data = $request->all();
		// authorize the credit card
        
        nlog($data);

		$response = $this->wepay_payment_driver->wepay->request('credit_card/authorize', array(
            'account_id'         => $this->wepay_payment_driver->company_gateway->getConfigField('accountId'),
		    'client_id'          => config('ninja.wepay.client_id'),
		    'client_secret'      => config('ninja.wepay.client_secret'),
		    'credit_card_id'     => $data['credit_card_id'],
		));

		// display the response
		print_r($response);
        nlog($response);
 	}   
}
